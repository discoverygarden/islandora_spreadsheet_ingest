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
class Ingest extends FormBase {

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

  protected $migrations;

  /**
   * Get the migrations upon which we're to operate.
   *
   * @return \Drupal\migrate\Migration[]
   *   The migrations, ordered according to their dependencies.
   */
  protected function getMigrations() {
    if ($this->migrations === NULL) {
      $names = $this->migrationStorage->getQuery()
        ->condition('migration_group', static::MG)
        ->execute();

      $migration_plus_migrations = $this->migrationStorage->loadMultiple($names);
      $this->migrations = $this->migrationPluginManager->createInstances($names, array_map(function ($a) { return $a->toArray(); }, $migration_plus_migrations));
    }

    return $this->migrations;
  }

  /**
   * Get the properties associated with destinations of the migrations.
   */
  protected function getDestinationProperties() {
    $props = [];

    foreach ($this->getMigrations() as $name => $migration) {
      $migration_options = [];

      $row_probe = new Row();
      $dp = $migration->getDestinationPlugin();
      if ($dp instanceof Entity) {
        $key = $dp->getPluginId();
        $bundle = $dp->getBundle($row_probe);
        $def = $this->typedDataManager->createDataDefinition($bundle ? "$key:$bundle" : $key);
        foreach ($def->getPropertyDefinitions() as $prop) {
          $migration_options["{$migration->id()}:{$prop->getName()}"] = $prop;
        }
      }
      else {
        throw new Exception('What are you trying to map to!?');
      }

      $props["{$this->t(':label (:id)', [':label' => $migration->label(), ':id' => $migration->id()])}"] = $migration_options;
    }

    return $props;
  }
  protected function getDestinationOptions() {
    return array_map(function ($sec) {
      return array_map(function ($prop) {
        return $this->t(':ind:label (:name)', [
          ':label' => $prop->getLabel(),
          ':name' => $prop->getName(),
          ':ind' => $prop->isRequired() ? '*' : '',
        ]);
      }, $sec);
    }, $this->getDestinationProperties());
  }

  protected function getSourceOptions() {
    $header = $this->spreadsheetService->getHeader($this->getTargetFile());
    return array_combine($header, $header);
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

    $form['spreadsheet']['target_file']['mappings'] = [
      '#type' => 'details',
      '#title' => $this->t('Mappings'),
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Source'),
          $this->t('Destination'),
          ['data' => $this->t('Weight'), 'class' => ['tabledrag-hide']],
        ],
        '#tableselect' => TRUE,
        '#empty' => $this->t('It be empty, yo.'),
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'group-weight',
          ],
        ],
        // TODO: Generate table entries from storage.
        1 => [
          '#attributes' => ['class' => ['draggable']],
          'source' => ['#markup' => $this->t('what')],
          'destination' => ['#markup' => $this->t('there')],
          'weight' => [
             '#type' => 'weight',
             '#default_value' => 1,
             '#wrapper_attributes' => [
               'class' => ['tabledrag-hide'],
             ],
           ],
        ],
        2 => [
          '#attributes' => ['class' => ['draggable']],
          'source' => ['#markup' => $this->t('what')],
          'destination' => ['#markup' => $this->t('there')],
          'weight' => [
            '#type' => 'weight',
            '#default_value' => 2,
            '#wrapper_attributes' => [
              'class' => ['tabledrag-hide'],
            ],
          ],
        ],
      ],
      'add_mapping' => [
        'source_column' => [
          '#type' => 'select',
          '#title' => $this->t('Source Columns'),
          '#options' => $this->getSourceOptions($form_state),
        ],
        'destination' => [
          '#type' => 'select',
          '#title' => $this->t('Destination'),
          '#options' => $this->getDestinationOptions(),
        ],
        'add_new_mapping' => [
          '#type' => 'submit',
          '#value' => $this->t('Add mapping'),
          '#submit' => ['::submitAddMapping']
        ],
      ],
      // TODO: Add a button for the deletion of selected entries.
      // TODO: Allow the addition/creation of new entries.
    ];

    $form['timing'] = [
      '#type' => 'details',
      '#group' => 'flow',
      '#title' => $this->t('Timing'),
      'enqueue' => [
        '#type' => 'radios',
        '#title' => $this->t('Processing'),
        '#options' => [
          'defer' => $this->t('Deferred'),
          'immediate' => $this->t('Immediate'),
        ],
        '#default_value' => 'defer',
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

  public function submitAddMapping(array &$form, FormStateInterface $form_state) {
    throw new Exception('Not implemented');
  }

}
