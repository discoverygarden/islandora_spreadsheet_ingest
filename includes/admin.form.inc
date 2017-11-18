<?php

/**
 * @file
 * Module admin form.
 */

/**
 * Administration form.
 */
function islandora_spreadsheet_ingest_admin_form(array $form, array &$form_state) {
  form_load_include($form_state, 'inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
  $templates = islandora_spreadsheet_ingest_get_templates();
dpm($templates, 'admin');
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Remove Templates'),
  );
  return $form;
}

/**
 * Administration form submit callback.
 */
function islandora_spreadsheet_ingest_admin_form_submit(array $form, array &$form_state) {

  dpm($form_state['values']);
}

/**
 * Add template form.
 */
function islandora_spreadsheet_ingest_add_template_form(array $form, array &$form_state) {
  $form['name'] = array(
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Template Name'),
  );
  $form['file'] = array(
    '#type' => 'managed_file',
    '#required' => TRUE,
    '#title' => t('Upload Template'),
    '#description' => t('Select a template file to upload. Must have the extension <strong>xsl</strong> or <strong>xslt</strong>.'),
    '#upload_location' => file_default_scheme() . '://',
    '#upload_validators' => array(
      'file_validate_extensions' => array('xsl xslt'),
    ),
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Add Template'),
  );
  return $form;
}

/**
 * Add template form submit callback.
 */
function islandora_spreadsheet_ingest_add_template_form_submit(array $form, array &$form_state) {
  form_load_include($form_state, 'inc', 'islandora_spreadsheet_ingest', 'includes/db');
  $form_state['redirect'] = 'admin/islandora/tools/islandora_spreadsheet_ingest';

  try {
    $id = islandora_spreadsheet_ingest_add_template(
      $form_state['values']['name'],
      $form_state['values']['file']
    );
  }
  catch( PDOException $Exception ) {
    drupal_set_message(t('Please choose a unique name when adding a template.'), 'error');
    return;
  }

  $managed_file = file_load($form_state['values']['file']);
  file_usage_add($managed_file, 'islandora_spreadsheet_ingest', 'template', $id);
  drupal_set_message(t('Template added.'));
}
