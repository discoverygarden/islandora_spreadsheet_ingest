<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Psr\Log\LoggerInterface;

class MigrationGroupDeriver implements MigrationGroupDeriverInterface {
  protected $logger;
  protected $entityTypeManager;
  protected $migrationGroupStorage;
  protected $cacheInvalidator;

  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    CacheTagsInvalidatorInterface $invalidator
  ) {
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationGroupStorage = $this->entityTypeManager->getStorage('migration_group');
    $this->cacheInvalidator = $invalidator;
  }

  public function deriveName(RequestInterface $request) {
    return "isi_request__{$request->id()}";
  }

  protected function invalidateTags() {
    $this->logger->debug('Invalidating cache for "migration_plugins"');
    $this->cacheInvalidator->invalidateTags(['migration_plugins']);
    $this->logger->info('Invalidated cache for "migration_plugins"');
  }

  public function create(RequestInterface $request) {
    assert($request->getActive());
    $this->logger->debug('Deriving migration group for {id}', ['id' => $request->id()]);

    $name = static::deriveName($request);
    $mg = $this->migrationGroupStorage->load($name);

    if (!$mg) {
      list(, $original) = explode(':', $reqeust->getOriginalMapping());
      $original_mg = $this->migrationGroupStorage->load($original);
      if ($original_mg) {
        $mg = $original_mg->createDuplicate()
          ->set('id', $name)
          ->set('label', $name);
      }
      else {
        $mg = $this->migrationGroupStorage->create([
          'id' => $name,
          'label' => $name,
          'description' => '',
        ]);
      }
    }

    // Setup the shared config on the group.
    $config = $mg->get('shared_configuration') ?? [];
    $config['source'] = [
      'plugin' => 'spreadsheet',
      'worksheet' => $request->getSheet()['sheet'],
      'track_changes' => TRUE,
      'file' => $request->getSheet()['file'],
      'header_row' => 1,
      'keys' => [
        'ID' => [
          'type' => 'integer',
        ],
      ],
    ];

    $deps = $mg->get('dependencies') ?? [];
    $deps['enforced'][$request->getConfigDependencyKey()][] = $request->getConfigDependencyName();

    $mg->set('shared_configuration', $config)
      ->set('dependencies', $deps)
      ->save();

    $this->invalidateTags();
  }

  public function delete(RequestInterface $request) {
    $this->logger->debug('Deleting migration group for {id}', ['id' => $request->id()]);
    try {
      $mg = $this->migrationGroupStorage->load($name);
      if ($mg) {
        $this->migrationGroupStorage->delete([$mg]);
        $this->logger->info('Deleted migration group for {id}.'. ['id' => $request->id()]);
      }
      else {
        $this->logger->debug
      }
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to delete {id}, with exception: {exception}', [
        'id' => $request->id(),
        'exception' => $e,
      );
    }
    finally {
      $this->invalidateTags();
    }
  }

}
