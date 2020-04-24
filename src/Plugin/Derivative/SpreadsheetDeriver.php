<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\Derivative;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\File\FileSystem;
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
   * Constructor.
   */
  public function __construct($base_plugin_id, EntityStorageInterface $file_entity_storage, ModuleHandlerInterface $module_handler, ArchiverManager $archiver_manager, FileSystem $fileSystem) {
    $this->fileEntityStorage = $file_entity_storage;
    $this->moduleHandler = $module_handler;
    $this->archiverManager = $archiver_manager;
    $this->fileSystem = $fileSystem;
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
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->moduleHandler->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');

    $templates = islandora_spreadsheet_ingest_get_templates();
    $ingests = islandora_spreadsheet_ingest_get_ingests();

    $this->derivatives = [];

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
        $yaml = Yaml::parse(file_get_contents($content_uri));

        // Setup new group, merge in group info.
        if (isset($yaml['migration_group'])) {
          $yaml['migration_group'] = "{$yaml['migration_group']}_{$ingest['id']}";
          foreach ($contents as $group_candidate_name) {
            if (strpos($group_candidate_name, '.migration_group.')) {
              $raw_group_name = $group_candidate_name;
              break;
            }
          }
          $group_uri = "zip://$zip_path#$raw_group_name";
          $group_yaml = Yaml::parse(file_get_contents($group_uri));
          $yaml = array_merge_recursive($group_yaml['shared_configuration'], $yaml);
        }
        // Make them their own migrations!
        $yaml['source']['file'] = $this->fileSystem->realpath($ingest_file->getFileUri());;
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
        $this->derivatives[$yaml['id']] = $yaml;
      }
    }

    return $this->derivatives;
  }

}
