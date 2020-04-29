<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\migrate\Plugin\MigrationPluginManager;

/**
 * Form for setting up ingests.
 */
class Ingest extends FormBase {

  /**
   * Constructor.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_invalidator, MigrationPluginManager $migration_plugin_manager, EntityStorageInterface $file_entity_storage) {
    $this->cacheInvalidator = $cache_invalidator;
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->fileEntityStorage = $file_entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache_tags.invalidator'),
      $container->get('plugin.manager.migration'),
      $container->get('entity_type.manager')->getStorage('file')
    );
  }

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
    $form_state->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');
    $templates = islandora_spreadsheet_ingest_get_templates();
    $ingests = islandora_spreadsheet_ingest_get_ingests();

    $form['#tree'] = TRUE;
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
    // Get statuses.
    $migrations = $this->migrationPluginManager->createInstances([]);
    $migration_statuses = [];
    foreach ($migrations as $migration_id => $migration) {
      if (strpos($migration_id, 'isimd:') !== 0) {
        continue;
      }
      $id_parts = explode('_', $migration_id);
      $migration_id = array_pop($id_parts);
      $migration_status = $migration->allRowsProcessed();
      if (!$migration_status ||
        (isset($migration_statuses[$migration_id]) && !$migration_statuses[$migration_id])
        ) {
        $migration_statuses[$migration_id] = FALSE;
      }
      else {
        $migration_statuses[$migration_id] = TRUE;
      }
    }
    // Populate table.
    $options = [];
    foreach ($ingests as $ingest) {
      $ingest_file = $this->fileEntityStorage->load($ingest['fid']);
      $template_file = $this->fileEntityStorage->load($templates[$ingest['template']]['fid']);
      $options[$ingest['id']] = [
        'csv' => $ingest_file->getFilename(),
        'template' => $template_file->getFilename(),
        'status' => $migration_statuses[$ingest['id']] ? $this->t('Complete') : $this->t('Incomplete'),
      ];
    }
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
      '#name' => 'delete_ingest',
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
      '#upload_location' => 'private://',
    ];
    $template_options = [];
    foreach ($templates as $template) {
      $file = $this->fileEntityStorage->load($template['fid']);
      $template_options[$template['id']] = $file->getFilename();
    }
    $form['new_ingest_fieldset']['template'] = [
      '#type' => 'select',
      '#title' => $this->t('Template'),
      '#options' => $template_options,
    ];
    $form['new_ingest_fieldset']['add'] = [
      '#type' => 'submit',
      '#name' => 'add_ingest',
      '#value' => $this->t('Queue Ingest'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#name'] == 'add_ingest') {
      if (empty($form_state->getValue(['new_ingest_fieldset', 'new_ingest']))) {
        $form_state->setError(
          $form['new_ingest_fieldset']['new_ingest'],
          $this->t('Please provide a source CSV.')
        );
      }
    }
    else {
      if (!array_filter($form_state->getValue(['ingests_fieldset', 'ingests']))) {
        $form_state->setError(
          $form['ingests_fieldset']['ingests'],
          $this->t('Please indicate one or more ingests.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->loadInclude('islandora_spreadsheet_ingest', 'inc', 'includes/db');

    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#name'] == 'add_ingest') {
      if (!empty($form_state->getValue(['new_ingest_fieldset', 'new_ingest']))) {
        $template = $form_state->getValue(['new_ingest_fieldset', 'template']);
        $file = $this->fileEntityStorage->load(reset($form_state->getValue(['new_ingest_fieldset', 'new_ingest'])));
        $file->setPermanent();
        $file->save();
        islandora_spreadsheet_ingest_add_ingest($file->id(), $template);
      }
    }
    else {
      $ingests = islandora_spreadsheet_ingest_get_ingests();
      $delete_ingests = array_filter($form_state->getValue(['ingests_fieldset', 'ingests']));
      if ($delete_ingests) {
        foreach ($delete_ingests as $ingest) {
          $file = $this->fileEntityStorage->load($ingests[$ingest]['fid']);
          $file_name = $file->getFilename();
          $file->delete();
          drupal_set_message($this->t('The ingest @filename has been deleted.', [
            '@filename' => $file_name,
          ]));
        }
        islandora_spreadsheet_ingest_delete_ingests($delete_ingests);
      }
    }
    // @see: https://www.drupal.org/project/drupal/issues/3001284
    $this->cacheInvalidator->invalidateTags(['migration_plugins']);
  }

}
