<?php

namespace Drupal\islandora_spreadsheet_ingest\Util;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Queue\QueueInterface;
use Drupal\dgi_migrate\MigrateBatchException;
use Drupal\dgi_migrate\StatusFilter;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_tools\MigrateExecutable;

/**
 * Class responsible for rolling back a migration in batches.
 */
class MigrationRollbackBatch extends MigrateExecutable {

  use DependencySerializationTrait {
    __sleep as traitSleep;
    __wakeup as traitWakeup;
  }

  /**
   * Stores the migration rows.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * Flag if we should exclusively consider failed and ignored rows to rollback.
   *
   * @var bool
   */
  protected bool $checkStatus;

  /**
   * Constructs a new MigrationRollbackBatch instance.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration interface.
   * @param \Drupal\migrate\MigrateMessageInterface $messenger
   *   The messenger service.
   * @param array $options
   *   An array of options.
   */
  public function __construct(
    MigrationInterface $migration,
    MigrateMessageInterface $messenger,
    array $options,
  ) {
    parent::__construct($migration, $messenger, $options);
    $this->checkStatus = (bool) ($options['checkStatus'] ?? FALSE);
  }

  /**
   * Prepare a batch array for execution for the given migration.
   *
   * @return array
   *   A batch array with operations and the like.
   *
   * @throws \Exception
   *   If the migration could not be enqueued successfully.
   */
  public function prepareBatch(): array {
    $result = $this->enqueue();
    if ($result === MigrationInterface::RESULT_COMPLETED) {
      return [
        'title' => $this->t('Rolling back migration: @migration', [
          '@migration' => $this->migration->id(),
        ]),
        'operations' => [
          [[$this, 'processBatch'], []],
        ],
        'finished' => [$this, 'finishBatch'],
      ];
    }
    else {
      throw new \Exception('Migration failed.');
    }
  }

  /**
   * Adds the migration rows to a queue.
   *
   * @return int
   *   One of the MigrationInterface::RESULT_* constants representing the state
   *   of queueing.
   */
  private function enqueue(): int {
    // Only begin the import operation if the migration is currently idle.
    if ($this->migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $this->message->display($this->t('Migration @id is busy with another operation: @status',
        [
          '@id' => $this->migration->id(),
          // XXX: Copypasta.
          // @See https://git.drupalcode.org/project/drupal/-/blob/154038f1401583a30e0ea7d9c19db02f37b10943/core/modules/migrate/src/MigrateExecutable.php#L156
          //phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
          '@status' => $this->t($this->migration->getStatusLabel()),
        ]), 'error');
      return MigrationInterface::RESULT_FAILED;
    }
    $this->getEventDispatcher()->dispatch(new MigrateRollbackEvent($this->migration, $this->message), MigrateEvents::PRE_ROLLBACK);
    $this->migration->setStatus(MigrationInterface::STATUS_ROLLING_BACK);
    $queue = $this->getQueue();
    $queue->deleteQueue();

    $id_map = $this->getIdMap();
    $iterator = $this->checkStatus ?
      new StatusFilter($id_map, StatusFilter::mapStatuses('failed,ignored')) :
      $id_map;
    foreach ($iterator as $row) {
      $queue->createItem([
        'iterator_value' => $row,
        'destination' => $id_map->currentDestination(),
        'source' => $id_map->currentSource(),
      ]);
    }

    return MigrationInterface::RESULT_COMPLETED;
  }

  /**
   * Process each batch to roll back all contained rows.
   *
   * @param array $context
   *   The batch context.
   */
  public function processBatch(&$context) : void {
    $sandbox =& $context['sandbox'];

    $queue = $this->getQueue();

    if (!isset($sandbox['total'])) {
      $sandbox['total'] = $queue->numberOfItems();
      if ($sandbox['total'] === 0) {
        $context['message'] = $this->t('Queue empty.');
        $context['finished'] = 1;
        return;
      }
    }

    $get_current = function (bool $pre_delete = FALSE) use (&$sandbox, $queue) {
      return $sandbox['total'] - $queue->numberOfItems() + ($pre_delete ? 1 : 0);
    };
    $update_finished = function (bool $pre_delete = FALSE) use (&$context, &$sandbox, $get_current) {
      $context['finished'] = $get_current($pre_delete) / $sandbox['total'];
    };
    try {
      $update_finished();
      while ($context['finished'] < 1) {
        $item = $queue->claimItem();
        if (!$item) {
          // XXX: Exceptions for flow control... maybe not the best, but works
          // for now... as such, let's allow it to pass translated messages.
          // phpcs:ignore DrupalPractice.General.ExceptionT.ExceptionT
          throw new MigrateBatchException($this->t('Queue exhausted.'), 1);
        }

        try {
          $status = $this->processFromQueue($item->data);
          $context['message'] = $this->t('Migration "@migration": @current/@total; rolled back row with IDs: (@ids)', [
            '@migration' => $this->migration->id(),
            '@current'   => $get_current(TRUE),
            '@ids'       => var_export($item->data, TRUE),
            '@total'     => $sandbox['total'],
          ]);
          if ($this->migration->getStatus() == MigrationInterface::STATUS_STOPPING) {
            // XXX: Exceptions for flow control... maybe not the best, but works
            // for now... as such, let's allow it to pass translated messages.
            // phpcs:ignore DrupalPractice.General.ExceptionT.ExceptionT
            throw new MigrateBatchException($this->t('Stopping "@migration" after @current of @total', [
              '@migration' => $this->migration->id(),
              '@current' => $get_current(TRUE),
              '@total' => $sandbox['total'],
            ]), 1);
          }
          elseif ($status === MigrationInterface::RESULT_INCOMPLETE) {
            // Force iteration, due to memory or time.
            // XXX: Don't want to pass a message here, as it would _always_ be
            // shown if this was run via the web interface.
            throw new MigrateBatchException();
          }
        }
        catch (MigrateBatchException $e) {
          // Rethrow to avoid the general handling below.
          throw $e;
        }
        catch (\Exception $e) {
          $context['message'] = $this->t('Migration "@migration": @current/@total; encountered exception processing row with IDs: (@ids); attempts exhausted, failing. Exception info:@n@ex', [
            '@migration' => $this->migration->id(),
            '@current'   => $get_current(TRUE),
            '@ids'       => var_export($item->data, TRUE),
            '@total'     => $sandbox['total'],
            '@ex'        => $e,
            '@n'         => "\n",
          ]);
        }
        finally {
          $queue->deleteItem($item);
        }

        $update_finished();
      }
    }
    catch (MigrateBatchException $e) {
      if ($msg = $e->getMessage()) {
        $context['message'] = $msg;
      }

      if ($e->getFinished() !== NULL) {
        $context['finished'] = $e->getFinished();
      }
      else {
        $update_finished();
      }
    }

  }

  /**
   * Handling rolling back a specific item.
   *
   * @param array $item
   *   An associative array containing:
   *   - source: The set of source IDs.
   *   - destination: The set of destination IDS.
   */
  public function processFromQueue(array $item) {
    $id_map = $this->getIdMap();
    $destination = $this->migration->getDestinationPlugin();

    if (!$item['destination']) {
      $this->message->display($this->t('Skipped processing due to null destination identifier.'));
      $id_map->delete($item['source']);
    }
    else {
      $this->getEventDispatcher()
        ->dispatch(new MigrateRowDeleteEvent($this->migration, $item['destination']), MigrateEvents::PRE_ROW_DELETE);
      $destination->rollback($item['destination']);
      $this->getEventDispatcher()
        ->dispatch(new MigrateRowDeleteEvent($this->migration, $item['destination']), MigrateEvents::POST_ROW_DELETE);
      $id_map->deleteDestination($item['destination']);
    }

    // Check for memory exhaustion.
    if (($return = $this->checkStatus()) != MigrationInterface::RESULT_COMPLETED) {
      return $return;
    }

    // If anyone has requested we stop, return the requested result.
    if ($this->migration->getStatus() == MigrationInterface::STATUS_STOPPING) {
      $return = $this->migration->getInterruptionResult();
      $this->migration->clearInterruptionResult();
    }

    return $return;
  }

  /**
   * Display success or error messages following the completion of processing.
   *
   * @param bool $success
   *   Indicates if the batch process was successful.
   * @param array $results
   *   The results of the batch process.
   * @param array $ops
   *   The operations performed.
   * @param int $interval
   *   The interval of the batch process.
   *
   * @return void
   *   void
   */
  public function finishBatch($success, $results, $ops, $interval): void {
    if (isset($results['errors']) && !empty($results['errors'])) {
      $this->messenger->addError($this->t('Rollback encountered errors.'));
      foreach ($results['errors'] as $e) {
        $error_message = is_object($e) ? json_encode($e) : (string) $e;
        $this->messenger->addError($this->t('Migration group rollback failed with exception: @e', ['@e' => $error_message]));
      }
    }

    $this->getEventDispatcher()->dispatch(new MigrateRollbackEvent($this->migration), MigrateEvents::POST_ROLLBACK);
    $this->migration->setStatus(MigrationInterface::STATUS_IDLE);
  }

  /**
   * Helper; build out the name of the queue.
   *
   * @return string
   *   The name of the queue.
   */
  public function getQueueName() : string {
    return "dgi_migrate__rollback_batch_queue__{$this->migration->id()}";
  }

  /**
   * Lazy-load the queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue implementation to use.
   */
  protected function getQueue() : QueueInterface {
    if (!isset($this->queue)) {
      $this->queue = \Drupal::queue($this->getQueueName(), TRUE);
    }

    return $this->queue;
  }

}
