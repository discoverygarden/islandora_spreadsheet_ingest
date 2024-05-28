<?php

namespace Drupal\islandora_spreadsheet_ingest\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Process\Process;

/**
 * Deferred ingest command.
 */
class DeferredIngestCommand extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * The queue of items to process.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The entity type manager, to access storages and load from them.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The migration group deriver, so we can lookup the group from the requests.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface
   */
  protected $migrationGroupDeriver;

  /**
   * Constructor.
   */
  public function __construct(QueueInterface $queue, EntityTypeManagerInterface $entity_type_manager, MigrationGroupDeriverInterface $migration_group_deriver) {
    $this->queue = $queue;
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationGroupDeriver = $migration_group_deriver;
  }

  /**
   * Run the deferred ingest.
   *
   * @command islandora_spreadsheet_ingest:deferred-ingest
   */
  public function deferredIngest() {
    $lock_path = 'temporary://isi_deferred_lock';
    $lock_pointer = fopen($lock_path, 'w+');
    if (flock($lock_pointer, LOCK_EX | LOCK_NB)) {
      $this->logger()->debug('Acquired lock; processing.');
      // Acquired lock, process.
      $request_storage = $this->entityTypeManager->getStorage('isi_request');
      while ($item = $this->queue->claimItem()) {
        // We only care that we made an attempt, so drop the item from the
        // queue.
        $this->queue->deleteItem($item);

        $request = $request_storage->load($item->data);

        $process = $this->processManager()->drush(
          $this->siteAliasmanager()->getSelf(),
          'migrate:batch-import',
          [],
          [
            'user' => $request->getOwner(),
            'group' => $this->migrationGroupDeriver->deriveName($request),
            'execute-dependencies' => TRUE,
            'debug' => TRUE,
          ]
        );
        $process->setTimeout(NULL);
        // XXX: Not sure if it will be necessary to capture stdout or stderr...
        // seems to require adding the the "::run()", if so.
        $process->run(function ($type, $buffer) {
          if ($type === Process::ERR) {
            fwrite(STDERR, $buffer);
          }
          else {
            fwrite(STDOUT, $buffer);
          }
        });
      }

      $this->logger()->debug('Processing complete; dropping lock.');
      if (flock($lock_pointer, LOCK_UN)) {
        $this->logger()->debug('Dropped lock; exiting.');
      }
      else {
        $this->logger()->warn('Failed to drop lock; exiting anyway.');
      }
    }
    else {
      $this->logger()->info('Could not acquire lock; aborting.');
    }
    fclose($lock_pointer);
  }

}
