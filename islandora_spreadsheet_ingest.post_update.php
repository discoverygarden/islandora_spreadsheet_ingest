<?php

/**
 * @file
 * Post-update hooks.
 */

/**
 * Set a value for islandora_spreadsheet_ingest.settings:schemes, if unset.
 */
function islandora_spreadsheet_ingest_post_update_set_default_schemes(&$sandbox) {
  $config = \Drupal::configFactory()->getEditable('islandora_spreadsheet_ingest.settings');
  if ($config->get('schemes') === NULL) {
    $config->set('schemes', [])->save();
    return \t('Set default value of an empty array for islandora_spreadsheet_ingest.settings:schemes.');
  }
  return \t('A value is already present for islandora_spreadsheet_ingest.settings:schemes.');
}
