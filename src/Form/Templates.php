<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityStorageInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for managing spreadsheet ingest templates.
 */
class Templates extends FormBase {

  /**
   * Is entity_type.manager service for `file`.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileEntityStorage;

  /**
   * Constructor.
   */
  public function __construct(EntityStorageInterface $file_entity_storage) {
    $this->fileEntityStorage = $file_entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('file')
    );
  }

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
    $form_state->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');
    $templates = islandora_spreadsheet_ingest_get_templates();

    $form['#tree'] = TRUE;
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

    $options = [];
    foreach ($templates as $template) {
      $file = $this->fileEntityStorage->load($template['fid']);
      $options[$template['id']] = [
        'template' => $file->getFilename(),
      ];
    }

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
      '#name' => 'delete_template',
      '#value' => $this->t('Delete'),
      '#submit' => [[$this, 'delete']],
      '#validate' => [[$this, 'validateDelete']],
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
      '#description' => $this->t('Only accepts .zip files.'),
      '#upload_location' => 'private://',
    ];
    $form['new_template_fieldset']['add'] = [
      '#type' => 'submit',
      '#name' => 'add_template',
      '#value' => $this->t('Add Template'),
      '#submit' => [[$this, 'add']],
      '#validate' => [[$this, 'validateAdd']],
    ];
    return $form;
  }

  /**
   * Validate adding a template.
   */
  public function validateAdd(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue(['new_template_fieldset', 'new_template']))) {
      $form_state->setError(
        $form['new_template_fieldset']['new_template'],
        $this->t('Please provide a template.')
      );
    }
  }

  /**
   * Validate deleting templates.
   */
  public function validateDelete(array &$form, FormStateInterface $form_state) {
    $delete_templates = array_filter($form_state->getValue(['templates_fieldset', 'templates']));
    if (!$delete_templates) {
      $form_state->setError(
        $form['templates_fieldset']['templates'],
        $this->t('Please indicate one or more templates.')
      );
      return;
    }
    $form_state->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');
    $ingests = islandora_spreadsheet_ingest_get_ingests();
    foreach ($delete_templates as $template) {
      foreach ($ingests as $ingest) {
        if ($ingest['template'] == $template) {
          $form_state->setError(
            $form['templates_fieldset']['templates'][$template],
            $this->t('Please make sure templates are not in use before deleting.')
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Remove templates.
   */
  public function delete(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');
    $templates = islandora_spreadsheet_ingest_get_templates();
    $delete_templates = array_filter($form_state->getValue(['templates_fieldset', 'templates']));
    if ($delete_templates) {
      foreach ($delete_templates as $template) {
        $file = $this->fileEntityStorage->load($templates[$template]['fid']);
        $file_name = $file->getFilename();
        $file->delete();
        drupal_set_message($this->t('The template @filename has been deleted.', [
          '@filename' => $file_name,
        ]));
      }
      islandora_spreadsheet_ingest_delete_templates($delete_templates);
    }
  }

  /**
   * Add a template.
   */
  public function add(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');
    if (!empty($form_state->getValue(['new_template_fieldset', 'new_template']))) {
      $file = $this->fileEntityStorage->load(reset($form_state->getValue(['new_template_fieldset', 'new_template'])));
      $file->setPermanent();
      $file->save();
      islandora_spreadsheet_ingest_add_template($file->id());
    }
  }

}
