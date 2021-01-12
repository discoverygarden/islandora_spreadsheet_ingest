<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Url;

use Symfony\Component\DependencyInjection\ContainerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\islandora_spreadsheet_ingest\Spreadsheet\ChunkReadFilter;

/**
 * Form for setting up ingests.
 */
class Mapping extends FormBase {

  protected $entityTypeManager;

  /**
   * Used to make sure new migrations are registered.
   *
   * @var Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheInvalidator;

  /**
   * Used to get migration information.
   *
   * @var Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * Is entity_type.manager service for `file`.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileEntityStorage;

  protected $typedDataManager;

  protected $spreadsheetService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->cacheInvalidator = $container->get('cache_tags.invalidator');
    $instance->migrationPluginManager = $container->get('plugin.manager.migration');
    $instance->typedDataManager = $container->get('typed_data_manager');

    $instance->fileEntityStorage = $instance->entityTypeManager->getStorage('file');
    $instance->migrationStorage = $instance->entityTypeManager->getStorage('migration');

    $instance->spreadsheetService = $container->get('islandora_spreadsheet_ingest.spreadsheet_service');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_mapping_form';
  }

  protected function getTargetFile() {
    $target_file = $this->store->get('target_file');
    if ($target_file) {
      return $this->fileEntityStorage->load(reset($target_file));
    }
    throw new \Exception('No target file selected.');

  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form += parent::buildForm($form, $form_state);

    $form['mappings'] = [
      '#type' => 'islandora_spreadsheet_ingest_migration_mappings',
      '#migration_group' => static::MG,
      '#source' => [
        'file' => $this->getTargetFile(),
        'sheet' => $this->store->get('sheet'),
      ],
    ];


    $form['actions'] += [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
      ],
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    throw new Exception('Not implemented');
  }

}
