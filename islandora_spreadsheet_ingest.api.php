<?php

/**
 * @file
 * Module API documentation.
 */

/**
 * This hook allows modules to provide templates.
 *
 * @return array
 *   An associative array mapping an id to an array with the keys 'name', 'uri'
 *   and 'dsid' containing a human readable name, URI, and output dsid for the
 *   template.
 */
function hook_islandora_spreadsheet_ingest_templates() {
  return array(
    array(
      'id' => 'my_template_id',
      'name' => 'my awesome template',
      'uri' => 'uri/to_my/template',
      'dsid' => 'MODS',
    ),
  );
}
