<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for managing spreadsheet ingest templates.
 *
 * @todo: implement.
 */
class Templates extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_templates_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // List.
    $form['templates_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Stored Templates'),
      '#open' => TRUE,
    ];

    $header = [
      'template' => $this
        ->t('Templates'),
    ];
    $options = [
      1 => [
        'template' => 'HARDCODED TEMPLATE VAL',
      ],
      2 => [
        'template' => 'HARDCODED TEMPLATE VAL',
      ],
      3 => [
        'template' => 'HARDCODED TEMPLATE VAL',
      ],
    ];
    $form['templates_fieldset']['templates'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this
        ->t('No stored templates.'),
    ];

    // Delete.
    $form['templates_fieldset']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
    ];

    // Add.
    $form['new_template_fieldset'] = [
      '#type' => 'details',
      '#title' => $this->t('Add a migrate template'),
      '#open' => TRUE,
    ];
    $form['new_template_fieldset']['new_template'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Template'),
      '#upload_validators' => ['file_validate_extensions' => ['zip']],
      '#description' => $this->t('Only accpets .zip files.'),
      '#upload_location' => 'temporoary://',
    ];
    $form['new_template_fieldset']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Template'),
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
