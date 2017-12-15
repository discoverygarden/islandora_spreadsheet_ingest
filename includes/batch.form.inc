<?php

/**
 * @file
 * Form controls to kick off the batch.
 */

/**
 * Batch form for initiating a spreadsheet ingest.
 */
function islandora_spreadsheet_ingest_batch_form($form, &$form_state) {
  module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
  module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/db');
  $dsids = isset($form_state['values']['template']) ? islandora_spreadsheet_ingest_get_template_dsids($form_state['values']['template']) : array();
  return array(
    'spreadsheet_parameters' => array(
      '#type' => 'fieldset',
      '#title' => t('Spreadsheet Ingest Parameters'),
      '#collapsible' => FALSE,
      'spreadsheet_file' => array(
        '#type' => 'managed_file',
        '#title' => t('CSV File'),
        '#required' => TRUE,
        '#description' => t('Select a CSV file to upload, delimited using the below-configured delimiters.'),
        '#upload_location' => file_default_scheme() . '://',
        '#upload_validators' => array(
          'file_validate_extensions' => array('csv'),
          'file_validate_size' => array(min((int) ini_get('post_max_size'), (int) ini_get('upload_max_filesize')) * 1024 * 1024),
        ),
      ),
      'template' => array(
        '#type' => 'select',
        '#title' => t('Template'),
        '#description' => t('Select the template to apply the given CSV to'),
        '#required' => TRUE,
        '#options' => islandora_spreadsheet_ingest_get_templates_as_form_options(),
        '#ajax' => array(
          'callback' => 'islandora_spreadsheet_ingest_batch_form_update_dsids',
          'wrapper' => 'islandora-spreadsheet-ingest-dsids',
          'effect' => 'fade',
        ),
      ),
      'dsid' => array(
        '#type' => 'select',
        '#title' => t('Output DSID'),
        '#description' => t('ID of the datastream that generated metadata should be placed in.'),
        '#required' => TRUE,
        '#options' => $dsids,
        '#prefix' => '<div id="islandora-spreadsheet-ingest-dsids">',
        '#suffix' => '</div>',
      ),
      'base_binary_path' => array(
        '#type' => 'textfield',
        '#title' => t('Base Binaries Folder'),
        '#description' => t('Path to a folder on the local filesystem where binaries are being kept. Files under the <strong>binary_file</strong> column will be sourced relative to this path.'),
        '#default_value' => variable_get('islandora_spreadsheet_ingest_default_binary_path', ''),
        '#element_validate' => array('islandora_spreadsheet_ingest_valid_base_binaries_folder'),
      ),
      'delimiters' => array(
        '#type' => 'fieldset',
        '#title' => t('CSV Delimiters'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        'cell_delimiter' => array(
          '#type' => 'textfield',
          '#title' => t('Cell Delimiter'),
          '#description' => t('Character to delimit individual cells in a row.'),
          '#default_value' => variable_get('islandora_spreadsheet_ingest_default_cell_delimiter', ','),
        ),
      ),
      'namespace' => array(
        '#type' => 'textfield',
        '#title' => t('Namespace'),
        '#description' => t('Ingested items will be given the next available PID in the given namespace. Leave blank to use the default configured namespace in Fedora.'),
        '#element_validate' => array('islandora_spreadsheet_ingest_valid_namespace'),
      ),
      'ingest_immediately' => array(
        '#type' => 'checkbox',
        '#title' => t('Ingest immediately'),
        '#description' => t('Whether to process the batch immediately after preprocessing.'),
        '#default_value' => TRUE,
      ),
    ),
    'help_text' => array(
      '#markup' => t('Column headers represent variables that will be passed into the selected XSLT and must only contain characters valid in XSLT qualified names. Due to the nature of XSLT, all variables defined by the template are required spreadsheet column headers. The following spreadsheet column headers are reserved and may be required:'),
    ),
    'help_table' => array(
      '#theme' => 'table',
      '#header' => array(
        t('Column Header'),
        t('Description'),
        t('Required'),
      ),
      '#rows' => array(
        array(
          'pid',
          t('A PID to assign this object'),
          t('No; if one is not given, a PID will be assigned in the given namespace.'),
        ),
        array(
          'parent_object',
          t('A PID representing the parent of the object.'),
          t('No, but omitting will generate an object with no parent'),
        ),
        array(
          'parent_predicate',
          t('The predicate relationship between this object and its given parent_object.'),
          t('No; defaults to "isMemberOfCollection"'),
        ),
        array(
          'parent_uri',
          t('The URI of the predicate relationship between this object and its given parent object.'),
          t('No; defaults to "info:fedora/fedora-system:def/relations-external#"'),
        ),
        array(
          'cmodel',
          t('A PID representing the content model to be applied to this object.'),
          t('Yes'),
        ),
        array(
          'binary_file',
          t("The relative path from the Base Binaries Folder to the file to use as the entry's OBJ datastream."),
          t('No'),
        ),
        array(
          'label',
          t('The label to give the object'),
          t('No, but omitting may generate objects with no labels'),
        ),
      ),
    ),
    'submit' => array(
      '#type' => 'submit',
      '#value' => t('Batch Ingest'),
    ),
  );
}

/**
 * Updates the selection section of the form.
 */
function islandora_spreadsheet_ingest_batch_form_update_dsids(array $form, array $form_state) {
  return $form['spreadsheet_parameters']['dsid'];
}

/**
 * Validates that the given base binaries folder exists.
 */
function islandora_spreadsheet_ingest_valid_base_binaries_folder($element, &$form_state, $form) {
  if (!empty($element['#value']) && !file_exists($element['#value'])) {
    form_error($element, t('Failed to resolve base binary path at @path', array(
      '@path' => $element['#value'],
    )));
  }
}

/**
 * Validates the given namespace.
 */
function islandora_spreadsheet_ingest_valid_namespace($element, &$form_state, $form) {
  module_load_include('inc', 'islandora', 'includes/utilities');
  if (!islandora_namespace_accessible($element['#value'])) {
    form_error($element, t('The namespace given is not valid.'));
  }
}

/**
 * Validation handler for spreadsheet ingest.
 */
function islandora_spreadsheet_ingest_batch_form_validate(&$form, &$form_state) {
  if (isset($form_state['values']['base_binary_path']) && !file_exists($form_state['values']['base_binary_path'])) {
    form_set_error('base_binary_path', t('Failed to resolve base binary path at @path', array(
      '@path' => $form_state['values']['base_binary_path'],
    )));
  }
}

/**
 * Submit handler for batch form.
 */
function islandora_spreadsheet_ingest_batch_form_submit(&$form, &$form_state) {
  $csv_file = file_load($form_state['values']['spreadsheet_file']);
  $form_state['values']['spreadsheet_file'] = $csv_file->uri;
  $csv_file->status = FILE_STATUS_PERMANENT;
  file_save($csv_file);

  $connection = islandora_get_tuque_connection();
  $preprocessor = new IslandoraSpreadsheetIngestBatch($connection, $form_state['values']);
  file_usage_add($csv_file, 'islandora_batch', 'islandora_batch_set', $preprocessor->getSetId());

  if ($form_state['values']['ingest_immediately']) {
    islandora_batch_ingest(array(
      'preprocessor' => $preprocessor,
    ));
  }
  else {
    islandora_batch_handle_preprocessor($preprocessor);
    drupal_set_message(t('Preprocessed <a href="/admin/reports/islandora_batch_queue/@set_id">set ID @set_id</a> with @total items.', array(
      '@set_id' => $preprocessor->getSetId(),
      '@total' => $preprocessor->getTotal(),
    )));
  }
  file_usage_delete($csv_file, 'islandora_batch', 'islandora_batch_set', $preprocessor->getSetId());
  file_delete($csv_file);
}
