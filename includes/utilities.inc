<?php

/**
 * @file
 * General helpers.
 */

/**
 * Get all templates.
 */
function islandora_spreadsheet_ingest_get_templates() {
  module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/db');

  $db_templates = islandora_spreadsheet_ingest_get_db_templates();
  $hook_templates = islandora_spreadsheet_ingest_get_hook_templates();
  $templates = array_merge($db_templates, $hook_templates);

  return $templates;
}

/**
 * Get all templates defined in hooks.
 */
function islandora_spreadsheet_ingest_get_hook_templates() {
  $templates = module_invoke_all('islandora_spreadsheet_ingest_templates');
  return $templates;
}
