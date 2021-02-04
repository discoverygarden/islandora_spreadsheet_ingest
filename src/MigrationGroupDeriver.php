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
  protected $fileStorage;
  protected $cacheInvalidator;
  protected $migrationStorage;

  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    CacheTagsInvalidatorInterface $invalidator
  ) {
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationGroupStorage = $this->entityTypeManager->getStorage('migration_group');
    $this->migrationStorage = $this->entityTypeManager->getStorage('migration');
    $this->fileStorage = $this->entityTypeManager->getStorage('file');
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
      list(, $original) = explode(':', $request->getOriginalMapping());
      $original_mg = $this->migrationGroupStorage->load($original);
      if ($original_mg) {
        $mg = $original_mg->createDuplicate()
          ->set('id', $name)
          ->set('label', $request->label());
      }
      else {
        $mg = $this->migrationGroupStorage->create([
          'id' => $name,
          'label' => $request->label(),
          'description' => '',
        ]);
      }
    }

    // Setup the shared config on the group.
    $config = $mg->get('shared_configuration') ?? [];
    $config['source'] = [
      'plugin' => 'spreadsheet',
      'worksheet' => $request->getSheet()['sheet'] ?
        $request->getSheet()['sheet'] :
        'nada',
      'track_changes' => TRUE,
      'file' => $this->fileStorage->load(reset($request->getSheet()['file']))->getFileUri(),
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
      $name = $this->deriveName($request);
      $mg = $this->migrationGroupStorage->load($name);
      if ($mg) {
        $this->migrationGroupStorage->delete([$mg]);
        $this->logger->info('Deleted migration group for {id}.', ['id' => $request->id()]);
      }
      else {
        $this->logger->debug('Migration group {id] does not exist, to be deleted.', ['id' => $request->id()]);
      }
    }
    catch (EntityStorageException $e) {
      $this->logger->error('Failed to delete {id}, with exception: {exception}', [
        'id' => $request->id(),
        'exception' => $e,
      ]);
    }
    finally {
      $this->invalidateTags();
    }
  }

}
