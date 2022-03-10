<?php

/**
 * @file
 * Post-update hooks.
 */

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Utility\UpdateException;

/**
 * Nuke migration config entities.
 *
 * Used to be islandora_spreadsheet_ingest_example_update_8100().
 */
function islandora_spreadsheet_ingest_example_post_update_delete_migraton_config_entities() {
  $storage = \Drupal::service('entity_type.manager')->getStorage('isi_request');
  $requests = $storage->loadMultiple();

  $entities = [
    'migrate_plus.migration.isi_file',
    'migrate_plus.migration.isi_media_audio',
    'migrate_plus.migration.isi_media_doc',
    'migrate_plus.migration.isi_media_file',
    'migrate_plus.migration.isi_media_image',
    'migrate_plus.migration.isi_media_video',
    'migrate_plus.migration.isi_node',
  ];

  $config_factory = \Drupal::configFactory();
  foreach ($entities as $entity) {
    $config_factory->getEditable($entity)->delete();
  }

  // XXX: Resave requests to remove the config dependency.
  foreach ($requests as $request) {
    $request->save();
  }
}

/**
 * Move over to point at our Spout spreadsheet source.
 */
function islandora_spreadsheet_ingest_example_post_update_move_group_to_spout() {
  $source = 'isi_spreadsheet';
  $source_plugin_manager = \Drupal::service('plugin.manager.migrate.source');
  if (!$source_plugin_manager->hasDefinition($source)) {
    throw UpdateException("The '$source' plugin is not available.");
  }

  $storage = \Drupal::service('entity_type.manager')->getStorage('migration_group');

  $entities = [
    'isi',
  ];

  foreach ($entities as $name) {
    $entity = $storage->load($name);

    $entity->set('shared_configuration', NestedArray::mergeDeep(
      $entity->get('shared_configuration'),
      [
        'source' => [
          'plugin' => 'isi_spreadsheet',
          'source_module' => 'islandora_spreadsheet_ingest',
        ],
      ]
    ))
      ->save();
  }
}
