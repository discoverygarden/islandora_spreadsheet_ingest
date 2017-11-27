<?php

/**
 * @file
 * Database helpers.
 */

/**
 * Get a template from the database.
 */
function islandora_spreadsheet_ingest_get_db_template($id) {
  $query = db_select('islandora_spreadsheet_ingest_templates', 'templates')
    ->fields('templates', array())
    ->condition('id', $id);

  return $query->execute()
    ->fetchAssoc();
}

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
function islandora_spreadsheet_ingest_add_template($name, $fid, $dsid) {
  return db_insert('islandora_spreadsheet_ingest_templates')
    ->fields(
      array(
        'fid' => $fid,
        'name' => $name,
        'dsid' => $dsid,
      )
    )
    ->execute();
}

/**
 * Update an existing template.
 */
function islandora_spreadsheet_ingest_update_template($id, $name, $fid, $dsid) {
  db_update('islandora_spreadsheet_ingest_templates')
    ->fields(
      array(
        'name' => $name,
        'fid' => $fid,
        'dsid' => $dsid,
      )
    )
    ->condition('id', $id)
    ->execute();
}
