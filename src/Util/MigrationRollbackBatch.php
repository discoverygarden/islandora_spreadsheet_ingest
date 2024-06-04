<?php

namespace Drupal\islandora_spreadsheet_ingest\Util;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\dgi_migrate\MigrationIterator;
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
   * @var MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Queue service.
   *
   * @var QueueInterface
   */
  protected QueueInterface $queue;

  /**
   * Iterates through migration rows.
   *
   * @var MigrationIterator
   */
  protected MigrationIterator $iterator;

  /**
   * Constructs a MigrationRollbackBatch object.
   *
   * @param MigrationInterface $migration
   *   The migration plugin instance.
   * @param MessengerInterface $messenger
   *   The messenger service.
   * @param QueueFactory $queue_factory
   *   The queue factory service.
   * @param array $options
   *   Additional options for the migration executable.
   *
   * @throws \Exception
   */
  public function __construct(
    MigrationInterface $migration,
    MessengerInterface $messenger,
    QueueFactory $queue_factory,
    array $options,
  ) {
    parent::__construct($migration, new MigrateMessage(), $options);
    $this->messenger = $messenger;
    $this->queue = $queue_factory->get('migration_rollback_queue');
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
  public function prepareBatch() {
    // Start by resetting the migration status.
    $this->migration->setStatus(MigrationInterface::STATUS_IDLE);

    // Initialize the iterator.
    $this->iterator = new MigrationIterator($this->getIdMap(), 'currentDestination');

    // Add each row to the queue.
    foreach ($this->iterator as $row) {
      $this->queue->createItem($row);
    }

    // Prepare the batch structure.
    $batch = [
      'title' => $this->t('Rolling back migration: @migration', [
        '@migration' => $this->migration->id(),
      ]),
      'operations' => [
        [[$this, 'processQueue'], []],
      ],
      'finished' => [$this, 'finishBatch'],
    ];

    return $batch;
  }

  /**
   * Process items from the queue.
   *
   * @param array $context
   *   The batch context.
   */
  public function processQueue(array &$context) {
    try {
      if ($item = $this->queue->claimItem()) {
        // Perform the rollback for the current row.
        $row = $item->data;
        $this->getEventDispatcher()->dispatch(new MigrateRowDeleteEvent($this->migration, $row), MigrateEvents::PRE_ROW_DELETE);
        $destination = $this->migration->getDestinationPlugin();
        $destination->rollback($row);
        $this->getEventDispatcher()->dispatch(new MigrateRowDeleteEvent($this->migration, $row), MigrateEvents::POST_ROW_DELETE);

        // Delete the row from the ID map.
        $id_map = $this->getIdMap();
        $id_map->deleteDestination($row);

        // Increment the processed row count.
        $context['results']['processed']++;

        // Release the item.
        $this->queue->deleteItem($item);
      } else {
        // If the queue is empty, mark the batch as finished.
        $context['finished'] = 1;
      }
    } catch (\Exception $e) {
      // Handle any exceptions that occur during rollback.
      $this->handleException($e, FALSE);
      $context['results']['errors'][] = $e;
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
        ['@id' => $this->migration->id(), '@status' => $this->t($this->migration->getStatusLabel())]), 'error');
      return MigrationInterface::RESULT_FAILED;
    }

    // Set the migration status to rolling back.
    $this->migration->setStatus(MigrationInterface::STATUS_ROLLING_BACK);

    $id_map = $this->getIdMap();
    $destination = $this->migration->getDestinationPlugin();
    $return = MigrationInterface::RESULT_COMPLETED;

    $this->iterator->rewind();

    while ($this->iterator->valid()) {
      if ($this->iterator->current() === NULL) {
        $this->message->display($this->t('Skipped processing due to null destination identifier.'));
        $source_key = $id_map->currentSource();
        $id_map->delete($source_key);
        continue;
      } else {
        $this->getEventDispatcher()
          ->dispatch(new MigrateRowDeleteEvent($this->migration, $this->iterator->current()), MigrateEvents::PRE_ROW_DELETE);
        $destination->rollback($this->iterator->current());
        $this->getEventDispatcher()
          ->dispatch(new MigrateRowDeleteEvent($this->migration, $this->iterator->current()), MigrateEvents::POST_ROW_DELETE);
        $id_map->deleteDestination($this->iterator->current());
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

      $this->iterator->next();
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
}
