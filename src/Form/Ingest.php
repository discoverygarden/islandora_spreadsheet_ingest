<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for setting up ingests.
 *
 * @todo: implement.
 */
class Ingest extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_ingest_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // List.
    $form['ingests_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Queued Ingests'),
      '#open' => TRUE,
    ];

    $header = [
      'csv' => $this
        ->t('CSV'),
      'template' => $this
        ->t('Template'),
      'status' => $this
        ->t('Status'),
    ];
    $options = [
      1 => [
        'csv' => 'HARDCODED CSV VAL',
        'template' => 'HARDCODED TEMPLATE VAL',
        'status' => 'DONE',
      ],
      2 => [
        'csv' => 'HARDCODED CSV VAL',
        'template' => 'HARDCODED TEMPLATE VAL',
        'status' => 'DONE',
      ],
      3 => [
        'csv' => 'HARDCODED CSV VAL',
        'template' => 'HARDCODED TEMPLATE VAL',
        'status' => 'Queued',
      ],
    ];
    $form['ingests_fieldset']['ingests'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this
        ->t('No queued ingests.'),
    ];

    // Delete.
    $form['ingests_fieldset']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
    ];

    // Add.
    $form['new_ingest_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Queue a CSV ingest'),
      '#open' => TRUE,
    ];
    $form['new_ingest_fieldset']['new_ingest'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Source CSV'),
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
      '#description' => $this->t('Please provide a .csv file.'),
      '#upload_location' => 'temporoary://',
    ];
    $options = [
      1 => 'HARDCODED TEMPLATE VAL',
      2 => 'HARDCODED TEMPLATE VAL',
      3 => 'HARDCODED TEMPLATE VAL',
    ];
    $form['new_ingest_fieldset']['template'] = [
      '#type' => 'select',
      '#title' => $this->t('Template'),
      '#options' => $options,
    ];
    $form['new_ingest_fieldset']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Queue Ingest'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
