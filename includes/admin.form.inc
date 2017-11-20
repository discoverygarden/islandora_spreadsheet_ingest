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
  $options = array();
  foreach ($templates as $template) {
    $options[$template['id']] = array(
      $template['name'],
    );
    if (isset($template['uri'])) {
      $options[$template['id']][] = l(t('Download'), file_create_url($template['uri']));
    }
    elseif (isset($template['fid'])) {
      $options[$template['id']][] = l(t('Download'), file_create_url(file_load($template['fid'])->uri));
      $options[$template['id']][] = l(t('Update'), "admin/islandora/tools/islandora_spreadsheet_ingest/{$template['id']}/update_template");
      $options[$template['id']][] = l(t('Delete'), "admin/islandora/tools/islandora_spreadsheet_ingest/{$template['id']}/delete_template");
    }
  }
  $form['templates'] = array(
    '#theme' => 'table',
    '#caption' => t('Templates'),
    '#header' => array(
      t('Name'),
      array('data' => t('Operations'), 'colspan' => 3),
    ),
    '#rows' => $options,
    '#empty' => t('No templates available.'),
  );
  return $form;
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
  catch (PDOException $exception) {
    drupal_set_message(t('Please choose a unique name when adding a template.'), 'error');
    return;
  }

  $managed_file = file_load($form_state['values']['file']);
  file_usage_add($managed_file, 'islandora_spreadsheet_ingest', 'template', $id);
  drupal_set_message(t('Template added.'));
}

/**
 * Form for deleting a template.
 */
function islandora_spreadsheet_ingest_delete_template_form($form, array &$form_state, $id) {
  form_load_include($form_state, 'inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
  $templates = islandora_spreadsheet_ingest_get_templates();
  foreach ($templates as $template) {
    if ($template['id'] == $id) {
      $name = $template['name'];
      break;
    }
  }
  return array(
    'template_id' => array(
      '#type' => 'hidden',
      '#value' => $id,
    ),
    'description' => array(
      '#prefix' => '<div>',
      '#markup' => t('Are you sure you want to delete the template <strong>%name</strong>? This action is irreversible.', array('%name' => $name)),
      '#suffix' => '</div>',
    ),
    'delete' => array(
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#name' => 'delete',
    ),
    'cancel' => array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'cancel',
    ),
  );
}

/**
 * Submit form delete callback.
 */
function islandora_spreadsheet_ingest_delete_template_form_submit(array $form, array &$form_state) {
  form_load_include($form_state, 'inc', 'islandora_spreadsheet_ingest', 'includes/db');
  $form_state['redirect'] = 'admin/islandora/tools/islandora_spreadsheet_ingest';
  if ($form_state['clicked_button']['#name'] == 'delete') {
    $id = $form_state['values']['template_id'];
    islandora_spreadsheet_ingest_purge_template($id);
    drupal_set_message(t('Successfully deleted the template.'));
  }
}

/**
 * Update template form.
 */
function islandora_spreadsheet_ingest_update_template_form(array $form, array &$form_state, $id) {
  form_load_include($form_state, 'inc', 'islandora_spreadsheet_ingest', 'includes/db');
  $template = islandora_spreadsheet_ingest_get_db_template($id);
  $form['template_id'] = array(
    '#type' => 'hidden',
    '#value' => $id,
  );
  $form['name'] = array(
    '#type' => 'textfield',
    '#required' => TRUE,
    '#title' => t('Template Name'),
    '#default_value' => $template['name'],
  );
  $form['file'] = array(
    '#type' => 'managed_file',
    '#title' => t('Upload Template'),
    '#description' => t('Select a template file to upload. Must have the extension <strong>xsl</strong> or <strong>xslt</strong>.'),
    '#upload_location' => file_default_scheme() . '://',
    '#upload_validators' => array(
      'file_validate_extensions' => array('xsl xslt'),
    ),
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update Template'),
  );
  return $form;
}

/**
 * Update template form submit callback.
 */
function islandora_spreadsheet_ingest_update_template_form_submit(array $form, array &$form_state) {
  $form_state['redirect'] = 'admin/islandora/tools/islandora_spreadsheet_ingest';

  $old_template = islandora_spreadsheet_ingest_get_db_template($form_state['values']['template_id']);
  if ($form_state['values']['file'] && $old_template['fid'] != $form_state['values']['file']) {
    $managed_file = file_load($form_state['values']['file']);
    $old_managed_file = file_load($old_template['fid']);
    file_usage_delete($old_managed_file, 'islandora_spreadsheet_ingest', 'template', $form_state['values']['template_id']);
    file_usage_add($managed_file, 'islandora_spreadsheet_ingest', 'template', $form_state['values']['template_id']);
    file_delete($old_managed_file);
  }

  islandora_spreadsheet_ingest_update_template(
    $form_state['values']['template_id'],
    $form_state['values']['name'],
    $form_state['values']['file'] ? $form_state['values']['file'] : $old_template['fid']
  );

  drupal_set_message(t('Template updated.'));
}
