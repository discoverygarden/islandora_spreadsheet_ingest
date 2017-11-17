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
  $form_state['redirect'] = 'admin/islandora/tools/islandora_spreadsheet_ingest';
  drupal_set_message(t('Template added.'));
  dpm($form_state['values']);
}
