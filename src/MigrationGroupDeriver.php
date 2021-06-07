<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Psr\Log\LoggerInterface;

/**
 * Migration group deriver.
 */
class MigrationGroupDeriver implements MigrationGroupDeriverInterface {

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Migration group storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterfaces
   */
  protected $migrationGroupStorage;

  /**
   * File storage.
   *
   * @var \Drupal\file\FileStorageInterfaces
   */
  protected $fileStorage;

  /**
   * Cache invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheInvalidator;

  /**
   * Migration storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $migrationStorage;

  /**
   * Constructor.
   */
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

  /**
   * {@inheritdoc}
   */
  public function deriveName(RequestInterface $request) {
    return "isi__{$request->id()}";
  }

  /**
   * Helper; clear the cache for migration plugins.
   */
  protected function invalidateTags() {
    $this->logger->debug('Invalidating cache for "migration_plugins"');
    $this->cacheInvalidator->invalidateTags(['migration_plugins']);
    $this->logger->info('Invalidated cache for "migration_plugins"');
  }

  /**
   * {@inheritdoc}
   */
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

    // Set the filepath and sheet as defined in the UI.
    $source = [
      'worksheet' => ($request->getSheet()['sheet'] ?
        $request->getSheet()['sheet'] :
        'nada'),
      'file' => $this->fileStorage->load(reset($request->getSheet()['file']))->getFileUri(),
    ];

    // Grab the original values.
    if (isset($config['source'])) {
      $source += $config['source'];
    }

    // Add on the rest of the defaults that may be missing.
    $source += [
      'plugin' => 'spreadsheet',
      'track_changes' => TRUE,
      'header_row' => 1,
      'keys' => [
        'ID' => [
          'type' => 'integer',
        ],
      ],
      'isi' => [
        'uid' => $request->getOwner(),
      ],
    ];
    $config['source'] = $source;

    $tags = ['isimd', 'isi_derived_migration'];
    if (!isset($config['migration_tags'])) {
      $config['migration_tags'] = $tags;
    }
    else {
      foreach ($tags as $tag) {
        if (!in_array($tag, $config['migration_tags'])) {
          $config['migration_tags'][] = $tag;
        }
      }
    }

    $config['migration_tags'][] = $this->deriveTag($request);

    $config['migration_tags'] = array_unique($config['migration_tags']);

    $deps = $mg->get('dependencies') ?? [];
    $deps['enforced'][$request->getConfigDependencyKey()][] = $request->getConfigDependencyName();

    $mg->set('shared_configuration', $config)
      ->set('dependencies', $deps)
      ->save();

    $this->invalidateTags();
  }

  /**
   * {@inheritdoc}
   */
  public function deriveTag(RequestInterface $request) {
    return "isimd:{$request->id()}";
  }

  /**
   * {@inheritdoc}
   */
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
        $this->logger->debug('Migration group {id} does not exist, to be deleted.', ['id' => $request->id()]);
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
