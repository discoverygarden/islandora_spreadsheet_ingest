<?php

/**
 * @file
 * Database helpers.
 */

/**
 * Get all templates from the database.
 */
function islandora_spreadsheet_ingest_get_db_templates() {
  $query = db_select('islandora_spreadsheet_ingest_templates', 'templates')
    ->fields('templates', array());

  return $query->execute()
    ->fetchAllAssoc('id', PDO::FETCH_ASSOC);
}

/**
 * Purge a template from the database.
 */
function islandora_spreadsheet_ingest_purge_template($id) {
  db_delete('islandora_spreadsheet_ingest_templates')
    ->condition('id', $id)
    ->execute();
}

/**
 * Add a template to the database.
 */
function islandora_spreadsheet_ingest_add_template($name, $fid) {
  return db_insert('islandora_spreadsheet_ingest_templates')
    ->fields(
      array(
        'fid' => $fid,
        'name' => $name,
      )
    )
    ->execute();
}

/**
 * Update an existing template.
 */
function islandora_spreadsheet_ingest_update_template($id, $name, $fid) {
  db_update('islandora_spreadsheet_ingest_templates')
  ->fields(
    array(
      'name' => $name,
      'fid' => $fid,
    )
  )
  ->condition('id', $id)
  ->execute();
}
