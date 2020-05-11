<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\Derivative;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Expose spreadsheet migrations as derivative plugins.
 *
 * @todo: figure out what to do about groups
 */
class SpreadsheetDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Is entity_type.manager service for `file`.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileEntityStorage;

  /**
   * Used to include files.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Used to inspect zips.
   *
   * @var Drupal\Core\Archiver\ArchiverManager
   */
  protected $archiverManager;

  /**
   * Used for URI handling.
   *
   * @var Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Logger with channel 'islandora_spreadsheet_ingest'.
   *
   * @var Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct($base_plugin_id, EntityStorageInterface $file_entity_storage, ModuleHandlerInterface $module_handler, ArchiverManager $archiver_manager, FileSystem $file_system, LoggerChannelFactoryInterface $logger_factory) {
    $this->fileEntityStorage = $file_entity_storage;
    $this->moduleHandler = $module_handler;
    $this->archiverManager = $archiver_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('islandora_spreadsheet_ingest');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')->getStorage('file'),
      $container->get('module_handler'),
      $container->get('plugin.manager.archiver'),
      $container->get('file_system'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getYaml($content_uri) {
    // @XXX: Yaml::parseFile didn't like my URI.
    try {
      $yaml = Yaml::parse(file_get_contents($content_uri));
    }
    catch (\Exception $e) {
      $yaml = FALSE;
      $this->logger->warning(
        'Issue reading "@uri" with message: @msg',
        ['@uri' => $content_uri, '@msg' => $e->getMessage()]
      );
    }
    return $yaml;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->moduleHandler->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');

    $templates = islandora_spreadsheet_ingest_get_templates();
    $ingests = islandora_spreadsheet_ingest_get_ingests();

    $group_map = [];
    foreach ($ingests as $ingest) {
      $ingest_file = $this->fileEntityStorage->load($ingest['fid']);
      $template_zip_file = $this->fileEntityStorage->load($templates[$ingest['template']]['fid']);
      $archive = $this->archiverManager->getInstance(['filepath' => $template_zip_file->getFileUri()]);
      $contents = $archive->listContents();

      $zip_path = $this->fileSystem->realpath($template_zip_file->getFileUri());
      foreach ($contents as $raw_name) {
        // Ignore macosx files.
        if (substr($raw_name, 0, 8) == '__MACOSX') {
          continue;
        }
        // Ignore group files.
        if (strpos($raw_name, '.migration_group.')) {
          continue;
        }
        $content_uri = "zip://$zip_path#$raw_name";
        $yaml = $this->getYaml($content_uri);
        if (!$yaml) {
          continue;
        }

        // Get group info.
        $yaml['migration_group'] = "{$yaml['migration_group']}_{$ingest['id']}";
        if (isset($group_map[$yaml['migration_group']])) {
          $group_yaml = $group_map[$yaml['migration_group']];
        }
        else {
          foreach ($contents as $group_candidate_name) {
            if (strpos($group_candidate_name, '.migration_group.')) {
              $raw_group_name = $group_candidate_name;
              break;
            }
          }
          $group_uri = "zip://$zip_path#$raw_group_name";
          $group_yaml = $this->getYaml($group_uri);
          if (!$group_yaml) {
            continue;
          }
          $group_map[$yaml['migration_group']] = $group_yaml;
        }

        // Merge in group info.
        // @see: migrate_plus_migration_plugins_alter.
        foreach ($group_yaml['shared_configuration'] as $key => $group_value) {
          $migration_value = $yaml[$key];
          // Where both the migration and the group provide arrays, replace
          // recursively (so each key collision is resolved in favor of the
          // migration).
          if (is_array($migration_value) && is_array($group_value)) {
            $merged_values = array_replace_recursive($group_value, $migration_value);
            $yaml[$key] = $merged_values;
          }
          // Where the group provides a value the migration doesn't, use the
          // group value.
          elseif (is_null($migration_value)) {
            $yaml[$key] = $group_value;
          }
          // Otherwise, the existing migration value overrides the group value.
        }

        // Make them their own migrations!
        $yaml['source']['file'] = $this->fileSystem->realpath($ingest_file->getFileUri());
        $yaml['id'] = "{$yaml['id']}_{$ingest['id']}";
        if (isset($yaml['migration_dependencies']['required'])) {
          foreach ($yaml['migration_dependencies']['required'] as &$required_dependency) {
            // @XXX: Deps getting 'isimd:' prepended somewhere in the pipes.
            $required_dependency = "isimd:{$required_dependency}_{$ingest['id']}";
          }
        }
        // Handle migration lookups.
        foreach ($yaml['process'] as &$process) {
          // Processes can be singular or an array.
          if (isset($process['plugin'])) {
            if ($process['plugin'] == 'migration_lookup') {
              $process['migration'] = "isimd:{$process['migration']}_{$ingest['id']}";
            }
          }
          elseif (is_array($process)) {
            foreach ($process as &$stepped_process) {
              if ($stepped_process['plugin'] == 'migration_lookup') {
                $stepped_process['migration'] = "isimd:{$stepped_process['migration']}_{$ingest['id']}";
              }
            }
          }
        }
        // Add our tag for easy migrations.
        if (!isset($yaml['migration_tags']) || !in_array('isimd', $yaml['migration_tags'])) {
          $yaml['migration_tags'][] = 'isimd';
        }
        $this->derivatives[$yaml['id']] = $yaml;
      }
    }

    return $this->derivatives;
  }

}
