<?php

/**
 * @file
 * Post-update hooks.
 */

use Drupal\Core\Utility\UpdateException;
use Drupal\migrate\Plugin\migrate\id_map\Sql;

/**
 * Migrate request entities from config to content.
 */
function islandora_spreadsheet_ingest_post_update_migrate_requests_from_config_to_content_0(array &$sandbox) {
  $config_factory = \Drupal::configFactory();
  if (!isset($sandbox['count'])) {
    $sandbox['names'] = $config_factory->listAll('islandora_spreadsheet_ingest.request');
    $sandbox['count'] = count($sandbox['names']);
    if ($sandbox['count'] === 0) {
      return "No entities to migrate.";
    }
    $sandbox['current'] = 0;
  }

  if (!($current = array_pop($sandbox['names']))) {
    throw new UpdateException("Unexpectedly failed to get item from array.");
  }

  // Create copy of request.
  $config = $config_factory->get($current);
  /** @var \Drupal\islandora_spreadsheet_ingest\RequestInterface $request */
  $request = \Drupal::entityTypeManager()->getStorage('isi_request')->create([
    'label' => $config->get('label'),
    'machine_name' => $config->get('id'),
    'sheet_file' => $config->get('sheet')['file'],
    'sheet_sheet' => $config->get('sheet')['sheet'],
    'mappings' => $config->get('mappings'),
    'original_mapping' => $config->get('originalMapping'),
    'owner' => $config->get('owner'),
    'active' => $config->get('active'),
  ]);
  $request->save();

  // Copy ID map and messages tables to the new entities, as those
  // associated with the old should be deleted in the next phase.
  $source_mg_name = "isi__{$config->get('id')}";
  $dest_mg_name = \Drupal::service('islandora_spreadsheet_ingest.migration_group_deriver')->deriveName($request);
  /** @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager */
  $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
  foreach (array_keys($request->getMappings()) as $name) {
    $source_migration_id = "{$source_mg_name}_{$name}";
    $dest_migration_id = "{$dest_mg_name}_{$name}";
    /** @var \Drupal\migrate\Plugin\MigrationInterface $source_migration */
    $source_migration = $migration_plugin_manager->createInstance($source_migration_id);
    /** @var \Drupal\migrate\Plugin\MigrationInterface $dest_migration */
    $dest_migration = $migration_plugin_manager->createInstance($dest_migration_id);
    $source_id_map = $source_migration->getIdMap();
    $dest_id_map = $dest_migration->getIdMap();
    if (!($source_id_map instanceof Sql)) {
      continue;
    }
    if (!($dest_id_map instanceof Sql)) {
      continue;
    }

    // XXX: Calling Sql::getDatabase() presently initializes things, to ensure
    // that the relevant tables exist.
    $source_id_map->getDatabase();
    $database = $dest_id_map->getDatabase();

    $database->insert($dest_id_map->mapTableName())
      ->from(
        $database->select($source_id_map->mapTableName(), 'm')
          ->fields('m')
      )
      ->execute();
    $database->insert($dest_id_map->messageTableName())
      ->from(
        $database->select($source_id_map->messageTableName(), 'm')
          ->fields('m')
      )
      ->execute();
  }

  $sandbox['#finished'] = ++$sandbox['current'] / $sandbox['count'];
}

/**
 * Delete disused request config entities.
 */
function islandora_spreadsheet_ingest_post_update_migrate_requests_from_config_to_content_1(array &$sandbox) {
  $config_factory = \Drupal::configFactory();
  if (!isset($sandbox['count'])) {
    $sandbox['names'] = $config_factory->listAll('islandora_spreadsheet_ingest.request');
    $sandbox['count'] = count($sandbox['names']);
    if ($sandbox['count'] === 0) {
      return "No entities to delete.";
    }
    $sandbox['current'] = 0;
  }

  if (!($current = array_pop($sandbox['names']))) {
    throw new UpdateException("Unexpectedly failed to get item from array.");
  }

  $config = $config_factory->getEditable($current);

  $file_usage_service = \Drupal::service('file.usage');
  $file_service = \Drupal::service('entity_type.manager')->getStorage('file');
  $fids = $config->get('sheet')['file'];
  if ($fids && ($file = $file_service->load(reset($fids)))) {
    $file_usage_service->delete(
      $file,
      'islandora_spreadsheet_ingest',
      'isi_request',
      $config->get('id'),
    );
  }
  $source_mg_name = "isi__{$config->get('id')}";
  /** @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager */
  $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
  foreach (array_keys($config->get('mappings')) as $name) {
    $source_migration_id = "{$source_mg_name}_{$name}";
    /** @var \Drupal\migrate\Plugin\MigrationInterface $source_migration */
    $source_migration = $migration_plugin_manager->createInstance($source_migration_id);
    $source_migration->getIdMap()->destroy();
  }

  $config->delete();
  $sandbox['#finished'] = ++$sandbox['current'] / $sandbox['count'];
}

/**
 * Set the default value for enable_config_ignore_integration.
 */
function islandora_spreadsheet_ingest_post_update_set_default_config_ignore_status() {
  \Drupal::configFactory()->getEditable('islandora_spreadsheet_ingest.settings')->set('enable_config_ignore_integration', TRUE);
}
