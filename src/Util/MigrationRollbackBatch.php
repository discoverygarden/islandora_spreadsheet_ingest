<?php

namespace Drupal\islandora_spreadsheet_ingest\Util;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
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
   * Manages migration plugin instances. It's used to create instances of migrations by their group ID.
   *
   * @var MigrationPluginManagerInterface
   */
  protected MigrationPluginManagerInterface $migrationPluginManager;
  protected MessengerInterface $messenger;

  public function __construct(
    MigrationInterface $migration,
    MessengerInterface $messenger,
    array              $options,
  ) {
    parent::__construct($migration, new MigrateMessage(), $options);
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
    return [
      'title' => $this->t('Rolling back migration: @migration', ['@migration' => $this->migration->id()]),
      'operations' => [
        [[$this, 'processBatch'], []],
      ],
      'finished' => [$this, 'finishBatch'],
    ];
  }

  /**
   * Process each batch to roll back all contained rows
   *
   * @param array $context
   * @return void
   */
  public function processBatch(array &$context): void {
    $context['message'] = $this->t('Processing of "@migration_id"', ['@migration_id' => $this->migration->id()]);

    $status = $this->rollback();

    if ($status === MigrationInterface::RESULT_COMPLETED) {
      $message = $this->t('Rollback completed', ['@id' => $this->migration->id()]);
      $this->messenger->addStatus($message);
    }
    else {
      $message = $this->t('Rollback of @name migration failed.', ['@name' => $this->migration->id()]);
      $this->messenger->addError($message);
    }

    $context['message'] = $this->t('"@migration_id" has been processed', ['@migration_id' => $this->migration->id()]);
  }


  /**
   * Display success or error messages following the completion of processing
   *
   * @param $success
   * @param $results
   * @param $ops
   * @param $interval
   * @return void
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
    }
  }
}
