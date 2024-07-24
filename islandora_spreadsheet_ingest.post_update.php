<?php

/**
 * @file
 * Post-update hooks.
 */

use Drupal\Core\Utility\UpdateException;

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

  $config = $config_factory->get($current);
  \Drupal::entityTypeManager()->getStorage('isi_request')->create([
    'label' => $config->get('label'),
    'machine_name' => $config->get('id'),
    'sheet_file' => $config->get('sheet')['file'],
    'sheet_sheet' => $config->get('sheet')['sheet'],
    'mappings' => $config->get('mappings'),
    'original_mapping' => $config->get('originalMapping'),
    'owner' => $config->get('owner'),
    'active' => $config->get('active'),
  ])->save();
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

  $config->delete();
  $sandbox['#finished'] = ++$sandbox['current'] / $sandbox['count'];
}
