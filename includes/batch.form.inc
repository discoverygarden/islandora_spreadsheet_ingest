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
  module_load_include('inc', 'xml_form_builder', 'includes/associations');
  $dsids = isset($form_state['values']['template']) ? drupal_map_assoc(islandora_spreadsheet_ingest_get_template_dsids($form_state['values']['template'])) : array();
  $form['#validate'] = array(
    'islandora_spreadsheet_ingest_csv_validate',
    'islandora_spreadsheet_ingest_batch_form_validate',
  );
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
  $form['spreadsheet_parameters']['transform'] = array(
    '#type' => 'checkbox',
    '#title' => ('Transform output'),
    '#description' => t('Transform the output datastream to another (ex: MODS to Dublin Core).'),
  );
  $form['spreadsheet_parameters']['transform_dsid'] = array(
    '#type' => 'textfield',
    '#title' => t('Transform DSID'),
    '#states' => array(
      'visible' => array(
        ':input[name="transform"]' => array(
          'checked' => TRUE,
        ),
      ),
      'required' => array(
        ':input[name="transform"]' => array(
          'checked' => TRUE,
        ),
      ),
    ),
  );

  // Remove the 'No Transform' option as it's redundant.
  $options = xml_form_builder_get_transforms();
  unset($options['No Transform']);
  $form['spreadsheet_parameters']['transform_template'] = array(
    '#type' => 'select',
    '#title' => t('Transform XSLT'),
    '#options' => $options,
    '#empty_option' => t('- Select -'),
    '#states' => array(
      'visible' => array(
        ':input[name="transform"]' => array(
          'checked' => TRUE,
        ),
      ),
      'required' => array(
        ':input[name="transform"]' => array(
          'checked' => TRUE,
        ),
      ),
    ),
  );
  $form['spreadsheet_parameters']['base_binary_path'] = array(
    '#type' => 'textfield',
    '#title' => t('Base Binaries Folder'),
    '#description' => t('Path to a folder on the local filesystem where binaries are being kept. Files under the <strong>@object_location</strong> (formerly <strong>binary_file</strong>) column will be sourced relative to this path. If the CSV file being uploaded does not contain an <strong>@object_location</strong> column, or the <strong>@object_location</strong> values are URLs, this path may be left blank. <strong>Files under @object_location must be uploaded before this ingest</strong>.', array(
      '@object_location' => 'object_location',
    )),
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
  if ($form_state['values']['transform']) {
    if (empty($form_state['values']['transform_dsid'])) {
      form_error($form['spreadsheet_parameters']['transform_dsid'], t('A Transform DSID is required.'));
    }
    if (empty($form_state['values']['transform_template'])) {
      form_error($form['spreadsheet_parameters']['transform_template'], t('A Transform XSLT is required.'));
    }
  }
}

/**
 * Custom validator for the .csv file.
 */
function islandora_spreadsheet_ingest_csv_validate($form, &$form_state) {
  // XXX: Validating the csv file is a bit too intensive to risk triggering it
  // multiple times, so we need to make sure only the submit triggers it, not
  // the ajax.
  if ($form_state['triggering_element']['#id'] == 'edit-submit' && !empty($form_state['values']['spreadsheet_file'])) {
    module_load_include('inc', 'islandora', 'includes/utilites');
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
    $fid = $form_state['values']['spreadsheet_file'];
    // Open the CSV file and start reading lines into an array.
    $csv_file = file_load($fid);
    file_save($csv_file);
    $csv_open_file = fopen($csv_file->uri, 'r');
    if (!$csv_open_file) {
      form_set_error('spreadsheet_file', t('Failed to open @file.', array('@file' => $csv_file->uri)));
    }
    $header_line = fgetcsv($csv_open_file, 0, $form_state['values']['cell_delimiter']);
    // Check if the .csv starts with a valid header line.
    if (!$header_line || $header_line === array(NULL)) {
      form_set_error('spreadsheet_file', t('Header line of @csv is either blank or malformed and needs review.', array(
        '@csv' => $csv_file->uri,
      )));
      return;
    }

    // Verify that headers only use characters valid in XSLT qualified names.
    foreach ($header_line as $header) {
      $local_name = '';
      // If prefixed QName.
      if (strpos($header, ':') !== FALSE) {
        // Validate prefix.
        list($prefix, $local_name) = explode(':', $header, 2);
        if (!islandora_spreadsheet_ingest_is_valid_xml_name($prefix)) {
          form_set_error('spreadsheet_file', t('The prefix @prefix in header @header of @csv is not a valid prefix for an XSLT qualified name.', array(
            '@prefix' => $prefix,
            '@header' => $header,
            '@csv' => $csv_file->uri,
          )));
        }
      }
      // If unprefixed QName.
      else {
        $local_name = $header;
      }
      // Validate local name.
      if (!islandora_spreadsheet_ingest_is_valid_xml_name($local_name)) {
        form_set_error('spreadsheet_file', t('The name @local in header @header of @csv is not a valid XSLT qualified name.', array(
          '@local' => $local_name,
          '@header' => $header,
          '@csv' => $csv_file->uri,
        )));
      }
    }

    // Verify that required headers are in the file.
    if (!in_array('cmodel', $header_line)) {
      form_set_error('spreadsheet_file', t('The required header, @cmodel, is missing from @csv.', array(
        // 'cmodel' will not change with the language.
        '@cmodel' => 'cmodel',
        '@csv' => $csv_file->uri,
      )));
    }

    // Key map will be used later to determine values for columns.
    $key_map = array_flip($header_line);

    // Keep track of rows for error reporting.
    $row_number = 0;

    $expected_row_length = count($header_line);
    // Check each row as they are read.
    while ($csv_open_file && !feof($csv_open_file)) {
      $row_number++;
      $row = fgetcsv($csv_open_file, 0, $form_state['values']['cell_delimiter']);
      if (!$row) {
        // Falsey rows are bad.
        form_set_error('spreadsheet_file', t('Row @row in @csv is invalid.', array(
          '@row' => $row_number,
          '@csv' => $csv_file->uri,
        )));
        continue;
      }
      if ($row === array(NULL)) {
        // XXX: As per the documentation: "A blank line in a CSV file will be
        // returned as an array comprising a single null field, and will not be
        // treated as an error."... We want to skip it.
        //
        // @see http://php.net/manual/function.fgetcsv.php
        continue;
      }

      // Verify rows are all the same length.
      if (count($row) != $expected_row_length) {
        form_set_error('spreadsheet_file', t('Row @row in @csv is missing elements.', array(
          '@row' => $row_number,
          '@csv' => $csv_file->uri,
        )));
        // With missing elements, existing elements cannot be validated since
        // they might not line up with the correct columns.
        continue;
      }

      // Check required/reserved columns.
      // Validate cmodel.
      if (in_array('cmodel', $header_line) && !in_array($row[$key_map['cmodel']], array_keys(islandora_get_content_models()))) {
        form_set_error('spreadsheet_file', t('Invalid cmodel found in row @row: @cmodel.', array(
          '@row' => $row_number,
          '@cmodel' => $row[$key_map['cmodel']],
        )));
      }
      // Check pid column.
      if (in_array('pid', $header_line) && (!empty($row[$key_map['pid']]) || $row[$key_map['pid']] === "0" || $row[$key_map['pid']] === 0)) {
        // Validate pids.
        if (!islandora_is_valid_pid($row[$key_map['pid']])) {
          form_set_error('spreadsheet_file', t('Invalid pid found in row @row: @pid', array(
            '@row' => $row_number,
            '@pid' => $row[$key_map['pid']],
          )));
        }
      }
      // Check that object_location and binary_file headers are not both set.
      if (in_array('object_location', $header_line) && in_array('binary_file', $header_line)) {
        form_set_error('spreadsheet_file', t('Header cannot include both object_location and binary_file.'));
      }

      // If header contains object_location or binary_file, and it is not a URL, validate file in that column.
      $object_header = NULL;
      if (in_array('object_location', $header_line)) {
        $object_header = 'object_location';
      }
      elseif (in_array('binary_file', $header_line)) {
        $object_header = 'binary_file';
      }
      if ($object_header) {
        $object_location = $row[$key_map[$object_header]];
        if ($object_location && !valid_url($object_location, TRUE)) {
          // Normalize the path on the way through.
          $path = islandora_spreadsheet_ingest_normalize_binary_file_path($form_state['values']['base_binary_path'], $object_location);
          if (!is_readable($path)) {
            form_set_error('spreadsheet_file', t('Failed to find or read the given binary file at @path on line @row of @csv', array(
              '@path' => $path,
              '@row' => $row_number,
              '@csv' => $csv_file->uri,
            )));
          }
        }
      }
    }
    fclose($csv_open_file);
  }
}

/**
 * Helper to validate XML names.
 */
function islandora_spreadsheet_ingest_is_valid_xml_name($xml_name) {
  // DOMElement with throw an exception if the xml name is invalid.
  try {
    new DOMElement($xml_name);
    return TRUE;
  }
  catch (DOMException $e) {
    return FALSE;
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

  module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
  $form_state['values']['self_transform'] = islandora_spreadsheet_ingest_get_template_default_self_transform($form_state['values']['template']);

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
    $set_id = $preprocessor->getSetId();
    drupal_set_message(t('Preprocessed <a href="@url">set ID @set_id</a> with @total items.', array(
      '@url' => "admin/reports/islandora_batch_queue/$set_id",
      '@set_id' => $set_id,
      '@total' => $preprocessor->getTotal(),
    )));
  }
  file_usage_delete($csv_file, 'islandora_batch', 'islandora_batch_set', $preprocessor->getSetId());
  file_delete($csv_file);
}
