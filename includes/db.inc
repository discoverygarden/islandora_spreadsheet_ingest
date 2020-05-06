<?php

/**
 * @file
 * DB helpers.
 */

use Drupal\Core\Database\Database;

/**
 * Get all templates.
 */
function islandora_spreadsheet_ingest_get_templates() {
  return Database::getConnection()
    ->select('islandora_spreadsheet_ingest_templates', 't')
    ->fields('t')
    ->execute()
    ->fetchAllAssoc('id', PDO::FETCH_ASSOC);
}

/**
 * Delete templates.
 *
 * @param array $ids
 *   IDs of templates to delete.
 */
function islandora_spreadsheet_ingest_delete_templates(array $ids) {
  Database::getConnection()
    ->delete('islandora_spreadsheet_ingest_templates')
    ->condition(
      'id',
      $ids,
      'IN'
    )
    ->execute();
}

/**
 * Add a template.
 *
 * @param int $fid
 *   FID of the template file.
 */
function islandora_spreadsheet_ingest_add_template($fid) {
  Database::getConnection()
    ->insert('islandora_spreadsheet_ingest_templates')
    ->fields([
      'fid' => $fid,
    ])
    ->execute();
}

/**
 * Get all ingests.
 */
function islandora_spreadsheet_ingest_get_ingests() {
  return Database::getConnection()
    ->select('islandora_spreadsheet_ingest_ingests', 'i')
    ->fields('i')
    ->execute()
    ->fetchAllAssoc('id', PDO::FETCH_ASSOC);
}

/**
 * Delete ingests.
 *
 * @param array $ids
 *   IDs of ingests to delete.
 */
function islandora_spreadsheet_ingest_delete_ingests(array $ids) {
  Database::getConnection()
    ->delete('islandora_spreadsheet_ingest_ingests')
    ->condition(
      'id',
      $ids,
      'IN'
    )
    ->execute();
}

/**
 * Add an ingest.
 *
 * @param int $fid
 *   FID of the source file.
 * @param int $template
 *   ID of the associated template.
 */
function islandora_spreadsheet_ingest_add_ingest($fid, $template) {
  Database::getConnection()
    ->insert('islandora_spreadsheet_ingest_ingests')
    ->fields([
      'fid' => $fid,
      'template' => $template,
    ])
    ->execute();
}
