<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Module settings form.
 */
class Admin extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $config = $this->config('islandora_spreadsheet_ingest.settings');
    $form['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Binary path whitelist'),
      '#default_value' => implode(',', $config->get('binary_directory_whitelist')),
      '#description' => $this->t('A comma seperated list of locations from which spreadsheet ingests can use binaries.'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_spreadsheet_ingest.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_spreadsheet_ingest.settings');
    $whitelist = explode(',', $form_state->getValue('paths'));
    $config->set('binary_directory_whitelist', $whitelist);
    $config->save();
    drupal_set_message($this->t('The whitelist has been updated.'));
  }

}
