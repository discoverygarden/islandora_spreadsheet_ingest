<?php

namespace Drupal\islandora_spreadsheet_ingest\Util;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\dgi_migrate\MigrationIterator;
use Drupal\dgi_migrate\StatusFilter;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateMessage;
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
   * Messenger service to display messages.
   *
   * @var Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Iterates through migration rows.
   *
   * @var Drupal\dgi_migrate\MigrationIteratorMigrationIterator
   */
  protected MigrationIterator $iterator;

  /**
   * Options.
   */
  private array $options;

  /**
   * Stores the migration rows
   *
   * @var QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * Batch context
   *
   * @var array
   */
  protected array $context;

  /**
   * Constructs a new MigrationRollbackBatch instance.
   *
   * @param \MigrationInterface $migration
   *   The migration interface.
   * @param \MessengerInterface $messenger
   *   The messenger service.
   * @param array $options
   *   An array of options.
   */
  public function __construct(
    MigrationInterface $migration,
    MessengerInterface $messenger,
    array $options,
  ) {
    parent::__construct($migration, new MigrateMessage(), $options);
    $this->options = $options;
    $this->messenger = $messenger;
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
    $id_map = $this->getIdMap();

    if (isset($this->options['checkStatus']) && $this->options['checkStatus'] == 1) {
      // Filter for failed rows.
      $failedStatuses = StatusFilter::mapStatuses('failed');
      $filteredIdMap = new StatusFilter($id_map, $failedStatuses);
      $this->iterator = new MigrationIterator($filteredIdMap, 'currentDestination');
    }
    else {
      $this->iterator = new MigrationIterator($id_map, 'currentDestination');
    }

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
   * Adds the migration rows to a queue
   *
   * @return int
   */
  private function enqueue(): int {
    $this->iterator->rewind();

    $this->queue = $this->getQueue();
    $this->queue->deleteQueue();

    while ($this->iterator->valid()) {
      $this->queue->createItem($this->iterator->current());
      $this->iterator->next();
    }

    return MigrationInterface::RESULT_COMPLETED;
  }

  /**
   * Process each batch to roll back all contained rows.
   *
   * @param array $context
   *   The batch context.
   *
   * @return int
   *   MigrationInterface result constant
   */
  public function processBatch(array &$context): int {
    try {
      // Announce that rollback is about to happen.
      $this->getEventDispatcher()->dispatch(new MigrateRollbackEvent($this->migration), MigrateEvents::PRE_ROLLBACK);

      // Initialize class context var
      $this->context = $context;
      $context['finished'] = 0;

      $context['message'] = $this->t('Processing of "@migration_id"', ['@migration_id' => $this->migration->id()]);

      $result = $this->rollback();

      $context['message'] = $this->t('"@migration_id" has been processed', ['@migration_id' => $this->migration->id()]);
      $this->messenger->addMessage(
        $this->t('"@migration_id" has been processed', ['@migration_id' => $this->migration->id()])
      );

      $context['results']['status'] = MigrationInterface::RESULT_COMPLETED;
      $context['sandbox']['total'] = 0;
      $context['finished'] = 1;

      return $result;
    }
    catch (\Exception $e) {
      $this->handleException($e, FALSE);
      $this->messenger->addError(
        $this->t(
          'Rollback encountered error while processing batch: @e.', ['@e' => $e]
        )
      );

      return MigrationInterface::RESULT_FAILED;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rollback() {
    // Only begin the rollback operation if the migration is currently idle.
    if ($this->migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $this->message->display($this->t(
        'Migration @id is busy with another operation: @status',
        ['@id' => $this->migration->id(), '@status' => $this->migration->getStatusLabel()]), 'error');
      return MigrationInterface::RESULT_FAILED;
    }

    // Set the migration status to rolling back.
    $this->migration->setStatus(MigrationInterface::STATUS_ROLLING_BACK);

    $id_map = $this->getIdMap();
    $destination = $this->migration->getDestinationPlugin();
    $return = MigrationInterface::RESULT_COMPLETED;

    while ($this->context['finished'] < 1) {
      $item = $this->queue->claimItem();

      if ($item->data === NULL) {
        $this->message->display($this->t('Skipped processing due to null destination identifier.'));
        $source_key = $id_map->currentSource();
        $id_map->delete($source_key);
        continue;
      }
      else {
        $this->getEventDispatcher()
          ->dispatch(new MigrateRowDeleteEvent($this->migration, $item->data), MigrateEvents::PRE_ROW_DELETE);
        $destination->rollback($item->data);
        $this->getEventDispatcher()
          ->dispatch(new MigrateRowDeleteEvent($this->migration, $item->data), MigrateEvents::POST_ROW_DELETE);
        $id_map->deleteDestination($item->data);
      }

      // Check for memory exhaustion.
      if (($return = $this->checkStatus()) != MigrationInterface::RESULT_COMPLETED) {
        break;
      }

      // If anyone has requested we stop, return the requested result.
      if ($this->migration->getStatus() == MigrationInterface::STATUS_STOPPING) {
        $return = $this->migration->getInterruptionResult();
        $this->migration->clearInterruptionResult();
        break;
      }
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
    return "dgi_migrate__batch_queue__{$this->migration->id()}";
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
