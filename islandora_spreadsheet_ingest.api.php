<?php

/**
 * @file
 * Module API documentation.
 */

/**
 * This hook allows modules to provide templates.
 *
 * @return array
 *   An associative array mapping an id to an array with the keys 'name' and
 *   'uri' containing a human readable name and URI for the template.
 */
function hook_islandora_spreadsheet_ingest_templates() {
  return array(
    'my_template_id' => array(
      'name' => 'my awesome template',
      'uri' => 'uri/to_my/template',
    ),
  );
}
