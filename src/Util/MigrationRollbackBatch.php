<?php

namespace Drupal\islandora_spreadsheet_ingest\Util;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_tools\MigrateExecutable;

/**
 * Class responsible for rolling back a migration in batches.
 */
class MigrationRollbackBatch extends MigrateExecutable {

  const MAX_RESCHEDULES = 3;

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
   * Queue instance.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  public function __construct(
    MigrationInterface $migration,
    MessengerInterface $messenger,
    array $options,
  ) {
    parent::__construct($migration, new MigrateMessage(), $options);
    $this->messenger = $messenger;
    $this->queue = $this->getQueue();
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
    $result = $this->enqueueRollbackItems();
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
      throw new \Exception('Migration rollback failed.');
    }
  }

  /**
   * Enqueue each item for rollback.
   */
  protected function enqueueRollbackItems(): int {
    $queue = $this->getQueue();
    $id_map = $this->getIdMap();
    $id_map->rewind();

    while ($id_map->valid()) {
      $destination_key = $id_map->currentDestination();
      if ($destination_key) {
        $map_row = $id_map->getRowByDestination($destination_key);
        if (!isset($map_row['rollback_action']) || $map_row['rollback_action'] == MigrateIdMapInterface::ROLLBACK_DELETE) {
          $queue->createItem([
            'migration_id' => $this->migration->id(),
            'destination_key' => $destination_key,
          ]);
        }
        else {
          $id_map->deleteDestination($destination_key);
        }
      }
      else {
        $source_key = $id_map->currentSource();
        $id_map->delete($source_key);
      }
      $id_map->next();
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
    $context['message'] = $this->t('Processing of "@migration_id"', ['@migration_id' => $this->migration->id()]);

    // Initialize the rescheduling counter if it doesn't exist.
    if (!isset($context['sandbox']['reschedule_count'])) {
      $context['sandbox']['reschedule_count'] = 0;
    }

    $result = $this->rollback();

    if ($result === MigrationInterface::RESULT_INCOMPLETE && $context['sandbox']['reschedule_count'] < static::MAX_RESCHEDULES) {

      // Increment the rescheduling counter.
      $context['sandbox']['reschedule_count']++;
      $context['finished'] = 0;
    }
    else {
      $context['finished'] = 1;
      $context['message'] = $this->t('"@migration_id" has been processed', ['@migration_id' => $this->migration->id()]);

      // If the maximum number of reschedules is reached, mark the process as finished.
      if ($context['sandbox']['reschedule_count'] >= static::MAX_RESCHEDULES) {
        $this->messenger->addError($this->t('The rollback process has reached the maximum number of reschedules.'));
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function rollback() {
    // Only begin the rollback operation if the migration is currently idle.
    if ($this->migration->getStatus() !== MigrationInterface::STATUS_IDLE) {
      $this->message->display($this->t(
        'Migration @id is busy with another operation: @status',
        ['@id' => $this->migration->id(), '@status' => $this->t($this->migration->getStatusLabel())]), 'error');
      return MigrationInterface::RESULT_FAILED;
    }

    // Announce that rollback is about to happen.
    $this->getEventDispatcher()->dispatch(new MigrateRollbackEvent($this->migration), MigrateEvents::PRE_ROLLBACK);

    // Set the migration status to rolling back.
    $this->migration->setStatus(MigrationInterface::STATUS_ROLLING_BACK);

    // Process items in the queue.
    $queue = $this->getQueue();
    $id_map = $this->getIdMap();
    $destination = $this->migration->getDestinationPlugin();
    $return = MigrationInterface::RESULT_COMPLETED;

    while ($item = $queue->claimItem()) {
      $destination_key = $item->data['destination_key'];
      if ($destination_key === NULL) {
        $this->message->display($this->t('Skipped processing due to null destination identifier.'));
        $queue->deleteItem($item);
        continue;
      }
      try {
        $this->getEventDispatcher()
          ->dispatch(new MigrateRowDeleteEvent($this->migration, $destination_key), MigrateEvents::PRE_ROW_DELETE);
        $destination->rollback($destination_key);
        $this->getEventDispatcher()
          ->dispatch(new MigrateRowDeleteEvent($this->migration, $destination_key), MigrateEvents::POST_ROW_DELETE);
        $id_map->deleteDestination($destination_key);
      }
      catch (\Exception $e) {
        $this->handleException($e, FALSE);
      }
      $queue->deleteItem($item);

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

    // Ensure that all items have been processed.
    if ($queue->numberOfItems() == 0) {
      // Notify modules that rollback attempt was complete.
      $this->getEventDispatcher()->dispatch(new MigrateRollbackEvent($this->migration), MigrateEvents::POST_ROLLBACK);
      $this->migration->setStatus(MigrationInterface::STATUS_IDLE);
    }
    else {
      $return = MigrationInterface::RESULT_INCOMPLETE;
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
    if (!$success || empty($results['errors'])) {
      $this->messenger->addError(
        $this->t(
          'Rollback encountered errors.'
        )
      );

      foreach ($results['errors'] as $e) {
        $this->messenger->addError(
          $this->t('Migration group rollback failed with exception: @e', ['@e' => $e]));
      }
    } else {
      $this->messenger->addMessage(
        $this->t(
          'Migration @migration rolled back successfully.', ['migration' => $this->migration->id()]
        )
      );
    }
  }

  /**
   * Helper; build out the name of the queue.
   *
   * @return string
   *   The name of the queue.
   */
  public function getQueueName(): string {
    return "migration_rollback_queue__{$this->migration->id()}";
  }

  /**
   * Lazy-load the queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue implementation to use.
   */
  protected function getQueue(): QueueInterface {
    if (!isset($this->queue)) {
      $this->queue = \Drupal::queue($this->getQueueName(), TRUE);
    }
    return $this->queue;
  }

}
