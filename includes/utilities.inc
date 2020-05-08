<?php

/**
 * @file
 * General helpers.
 */

/**
 * Get all template information.
 */
function islandora_spreadsheet_ingest_get_templates() {
  module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/db');

  $db_templates = islandora_spreadsheet_ingest_get_db_templates();
  foreach ($db_templates as $tid => &$template) {
    $template['dsids'] = islandora_spreadsheet_ingest_get_db_template_dsids($tid);
  }
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

/**
 * Get all templates as key/value pairs useful for #options.
 */
function islandora_spreadsheet_ingest_get_templates_as_form_options() {
  $options = array();
  $templates = islandora_spreadsheet_ingest_get_templates();
  foreach ($templates as $template) {
    $options[$template['id']] = $template['name'];
  }
  return $options;
}

/**
 * Gets an individual template definition.
 *
 * @param string $id
 *   The template ID to get.
 *
 * @return array|null
 *   The template information, or NULL if none exist by the given ID.
 */
function islandora_spreadsheet_ingest_get_template($id) {
  $templates = islandora_spreadsheet_ingest_get_templates();
  foreach ($templates as $template) {
    if ($template['id'] == $id) {
      return $template;
    }
  }
  return NULL;
}

/**
 * Gets the DSIDs associated with a template.
 */
function islandora_spreadsheet_ingest_get_template_dsids($id) {
  $template = islandora_spreadsheet_ingest_get_template($id);
  return $template['dsids'];
}

/**
 * Gets the correct default self transform for a template.
 */
function islandora_spreadsheet_ingest_get_template_default_self_transform($id) {
  $template = islandora_spreadsheet_ingest_get_template($id);
  if (!isset($template['self_transform'])) {
    return 'No Transform';
  }
  return $template['self_transform'];
}

/**
 * Helper function for normalizing the object_location path for binary files.
 */
function islandora_spreadsheet_ingest_normalize_binary_file_path($folder_path, $file_path) {
  return rtrim($folder_path, '/') . '/' . ltrim($file_path, '/');
}

/**
 * Validats whether the MODS namespace declarations begin with xmlns.
 *
 * It does this by using the simplexmlelement and verifying that the first
 * key in the document's namespace is empty.
 *
 * @param string $mods_xml
 *   The MODS content as a string.
 *
 * @return bool
 *   True if the first is empty, and false if it is populated.
 */
function islandora_spreadsheet_ingest_validate_namespaces($mods_xml) {
  $simple_xml = simplexml_load_string($mods_xml);
  $doc_namespaces = $simple_xml->getDocNamespaces();
  return array_keys($doc_namespaces)[0] == '';
}

/**
 * Ensures the the xmlns is listed first in the declarations.
 *
 * @param string $mods_xml
 *   The MODS content as a string.
 *
 * @return string
 *   The updated string.
 */
function islandora_spreadsheet_ingest_update_namespaces($mods_xml) {
  $transform_path = drupal_get_path('module', 'islandora_spreadsheet_ingest') . "/transforms/correct_mods_declaration.xslt";
  $mods = new DOMDocument();
  $mods->loadXML($mods_xml);
  $xslt = new XSLTProcessor();

  $xsl_transform = new DOMDocument();
  $xsl_transform->load($transform_path);
  $xslt->importStylesheet($xsl_transform);

  $updated_mods = $xslt->transformToDoc($mods);
  $updated_mods_xml = $updated_mods->saveXML();
  return $updated_mods_xml;
}
