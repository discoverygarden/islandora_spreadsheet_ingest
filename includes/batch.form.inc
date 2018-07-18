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
  $dsids = isset($form_state['values']['template']) ? drupal_map_assoc(islandora_spreadsheet_ingest_get_template_dsids($form_state['values']['template'])) : array();
  $form['spreadsheet_parameters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Spreadsheet Ingest Parameters'),
    '#collapsible' => FALSE,
  );
  $form['spreadsheet_parameters']['spreadsheet_file'] = array(
    '#type' => 'managed_file',
    '#title' => t('CSV File'),
    '#required' => TRUE,
    '#description' => t('Select a CSV file to upload, delimited using the below-configured delimiters.'),
    '#upload_location' => file_default_scheme() . '://',
    '#upload_validators' => array(
      'file_validate_extensions' => array('csv'),
      'file_validate_size' => array(min((int) ini_get('post_max_size'), (int) ini_get('upload_max_filesize')) * 1024 * 1024),
      'islandora_spreadsheet_ingest_csv_validate' => array(),
    ),
  );
  $form['spreadsheet_parameters']['template'] = array(
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
  );
  $form['spreadsheet_parameters']['dsid'] = array(
    '#type' => 'select',
    '#title' => t('Output DSID'),
    '#description' => t('ID of the datastream that generated metadata should be placed in.'),
    '#required' => TRUE,
    '#options' => $dsids,
    '#prefix' => '<div id="islandora-spreadsheet-ingest-dsids">',
    '#suffix' => '</div>',
  );
  $form['spreadsheet_parameters']['base_binary_path'] = array(
    '#type' => 'textfield',
    '#title' => t('Base Binaries Folder'),
    '#description' => t(
      'Path to a folder on the local filesystem where binaries are being kept.' .
      ' Files under the <strong>binary_file</strong> column will be sourced relative to this path.' .
      ' If the CSV file being uploaded does not contain a <strong>binary_file</strong> column, this path may be left blank.' .
      ' <strong>Files under binary_file must be uploaded before this ingest</strong>.'),
    '#default_value' => variable_get('islandora_spreadsheet_ingest_default_binary_path', ''),
    '#element_validate' => array('islandora_spreadsheet_ingest_valid_base_binaries_folder'),
  );
  $form['spreadsheet_parameters']['delimiters'] = array(
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
  );
  $form['spreadsheet_parameters']['paged_content'] = array(
    '#type' => 'checkbox',
    '#title' => t('Contains paged content objects'),
    '#default_value' => FALSE,
  );
  $form['spreadsheet_parameters']['paged_content_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Paged content derivatives'),
    '#states' => array(
      'visible' => array(
        ':input[name="paged_content"]' => array("checked" => TRUE),
      ),
    ),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['spreadsheet_parameters']['paged_content_fieldset']['paged_content_create_pdfs'] = array(
    '#type' => 'checkbox',
    '#title' => t('Create PDFs'),
    '#description' => t('Whether or not we should generate PDFs for the paged content parent objects after ingesting all of the pages.'),
  );
  if (module_exists('islandora_ocr') && module_exists('islandora_paged_content')) {
    module_load_include('inc', 'islandora_paged_content', 'includes/utilities');
    $book_derive = islandora_paged_content_get_enabled_ingest_derivatives('islandora_book');
    $newspaper_derive = islandora_paged_content_get_enabled_ingest_derivatives('islandora_newspaper');
    if ($book_derive['ocr'] || $newspaper_derive['ocr']) {
      $form['spreadsheet_parameters']['paged_content_fieldset']['paged_content_generate_ocr'] = array(
        '#type' => 'checkbox',
        '#title' => t('Generate OCR'),
        '#description' => t('Generate OCR for the pages of each paged content object.'),
        '#default_value' => TRUE,
      );
      $form['spreadsheet_parameters']['paged_content_fieldset']['paged_content_generate_hocr'] = array(
        '#type' => 'checkbox',
        '#title' => t('Generate HOCR'),
        '#description' => t('Generate HOCR for the pages of each paged content object.'),
        '#default_value' => TRUE,
      );
      $form['spreadsheet_parameters']['paged_content_fieldset']['paged_content_aggregate_ocr'] = array(
        '#type' => 'checkbox',
        '#title' => t('Aggregate OCR'),
        '#description' => t('Consolidate the page OCR and add it to the paged content parent objects after ingesting all of the pages.'),
        '#default_value' => FALSE,
        '#states' => array(
          'invisible' => array(
            ':input[name="paged_content_generate_ocr"]' => array('checked' => FALSE),
          ),
        ),
      );
    }
  }
  $form['spreadsheet_parameters']['namespace'] = array(
    '#type' => 'textfield',
    '#title' => t('Namespace'),
    '#description' => variable_get('islandora_namespace_restriction_enforced', FALSE) ?
    t("Values allowed: %pids", array(
      '%pids' => variable_get('islandora_pids_allowed', ""),
    )) :
    t("Ingested items will be given the next available PID in the given
      namespace. Leave blank to use the default configured namespace
      in Fedora."),
    '#element_validate' => array('islandora_spreadsheet_ingest_valid_namespace'),
  );
  $form['spreadsheet_parameters']['ingest_immediately'] = array(
    '#type' => 'checkbox',
    '#title' => t('Ingest immediately'),
    '#description' => t('Whether to process the batch immediately after preprocessing.'),
    '#default_value' => TRUE,
  );
  $form['spreadsheet_parameters']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Batch Ingest'),
  );
  return $form;
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
  if (
    isset($form_state['values']['base_binary_path']) &&
    !empty($form_state['values']['base_binary_path']) &&
    !file_exists($form_state['values']['base_binary_path'])
  ) {
    form_set_error('base_binary_path', t('Failed to resolve base binary path at @path', array(
      '@path' => $form_state['values']['base_binary_path'],
    )));
  }
}

/**
 * Custom upload validator for the .csv ingest file.
 * @param stdClass $csv_file
 *   The file uploaded in the spreadsheet_file element of the form.
 *
 * @return array
 *   An array containing errors, empty if the file is valid.
 */
function islandora_spreadsheet_ingest_csv_validate(stdClass $csv_file) {
  // Open the CSV file and start reading lines into an array.

  // Falsey rows are bad.

  // Verify rows are all the same length.

  // Check required/reserved columns.

  // Return empty array to indicate all checks passed.
  return array();
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
