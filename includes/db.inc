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
  islandora_spreadsheet_ingest_remove_template_dsid_associations($id);
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

/**
 * Gets an array of all DSIDs associated with a template.
 */
function islandora_spreadsheet_ingest_get_db_template_dsids($tid) {
  $results = db_select('islandora_spreadsheet_ingest_dsids', 'dsids')
    ->fields('dsids', array('dsid'))
    ->condition('tid', $tid)
    ->execute()
    ->fetchAll(PDO::FETCH_ASSOC);
  $get_dsid = function ($result) {
    return $result['dsid'];
  };
  return drupal_map_assoc(array_map($get_dsid, $results));
}

/**
 * Associate a template with the given DSIDs.
 *
 * @return bool
 *   Whether it was successful, for messaging purposes.
 */
function islandora_spreadsheet_ingest_set_dsid_associations($tid, array $dsids) {
  try {
    $transaction = db_transaction();
    islandora_spreadsheet_ingest_remove_template_dsid_associations($tid);
    $query = db_insert('islandora_spreadsheet_ingest_dsids')
      ->fields(array(
        'tid',
        'dsid',
      ));
    foreach ($dsids as $dsid) {
      $query->values(array(
        'tid' => $tid,
        'dsid' => $dsid,
      ));
    }
    $query->execute();
  }
  catch (Exception $e) {
    watchdog('islandora_spreadsheet_ingest_db',
      'Error occurred attempting to set DSID associations for template with ID @tid; changes have not been made: @e.',
      array('@tid' => $tid, '@e' => $e->getMessage()),
      WATCHDOG_ERROR);
    $transaction->rollback();
    return FALSE;
  }
  return TRUE;
}

/**
 * Remove DSID associations from the given template.
 */
function islandora_spreadsheet_ingest_remove_template_dsid_associations($tid) {
  db_delete('islandora_spreadsheet_ingest_dsids')
    ->condition('tid', $tid)
    ->execute();
}
