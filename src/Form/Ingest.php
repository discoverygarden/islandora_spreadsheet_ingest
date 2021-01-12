<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

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

  const MG = 'islandora_spreadsheet_example';

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

  /**
   * Constructor.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_invalidator, MigrationPluginManager $migration_plugin_manager, EntityTypeManagerInterface $entity_type_manager, TypedDataManagerInterface $typed_data_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheInvalidator = $cache_invalidator;
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->typedDataManager = $typed_data_manager;

    $this->fileEntityStorage = $this->entityTypeManager->getStorage('file');
    $this->migrationStorage = $this->entityTypeManager->getStorage('migration');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache_tags.invalidator'),
      $container->get('plugin.manager.migration'),
      $container->get('entity_type.manager'),
      $container->get('typed_data_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_ingest_form';
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

  protected function getSourceOptions(FormStateInterface $form_state) {
    $header = $this->getHeader($form_state);
    return array_combine($header, $header);
  }

  protected function getTargetFile(FormStateInterface $form_state) {
    $target_file = $form_state->getValue('target_file');
    if ($target_file) {
      return $this->fileEntityStorage->load(reset($target_file));
    }
    throw new \Exception('No target file selected.');

  }

  protected function getSpreadsheetReader(FormStateInterface $form_state) {
    $target_file = $form_state->getValue('target_file');
    if ($target_file) {
      $reader = IOFactory::createReaderForFile($this->getTargetFile($form_state)->uri->first()->getString());
      // XXX: Not really dealing with writing here... might as well inform 
      $reader->setReadDataOnly(TRUE);
      return $reader;
    }
    throw new \Exception('No target file from which to create a reader.');
  }

  protected function getSpreadsheetOptions(FormStateInterface $form_state) {
    $target_file = $form_state->getValue('target_file');
    if ($target_file) {
      $reader = $this->getSpreadsheetReader($form_state);;
      $lister = [$reader, 'listWorksheetNames'];
      return is_callable($lister) ?
        call_user_func($lister, $target_file) :
        // XXX: Need to provide _some_ name for things like CSVs.
        [$this->t('Irrelevant/single-sheet format')];
    }
    return [];
  }

  protected function getHeader(FormStateInterface $form_state) {
    try {
      $reader = $this->getSpreadsheetReader($form_state);
    }
    catch (\Exception $e) {
      return [];
    }
    $constrain = [$reader, 'setLoadSheetsOnly'];
    if (is_callable($constrain)) {
      call_user_func($constrain, $form_state->getValue('sheet'));
    }
    $filter = new ChunkReadFilter(0, 1);
    $reader->setReadFilter($filter);

    $target = $this->getTargetFile($form_state);

    $loaded = $reader->load($target->uri->first()->getString());

    $header = [];

    foreach ($loaded->getActiveSheet()->getRowIterator() as $row) {
      $cell_iterator = $row->getCellIterator();
      $cell_iterator->setIterateOnlyExistingCells(FALSE);

      foreach ($cell_iterator as $cell) {
        $header[] = $cell->getValue();
      }
    }

    dsm($header, 'asdf');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['flow'] = [
      '#type' => 'vertical_tabs',
    ];

    $target_file = $form_state->getValue('target_file');
    $sheet_options = $this->getSpreadsheetOptions($form_state);
    $form['spreadsheet'] = [
      '#type' => 'details',
      '#group' => 'flow',
      '#title' => $this->t('Spreadsheet selection'),
      'target_file' => [
        '#type' => 'managed_file',
        '#title' => $this->t('Target file'),
        '#upload_validators' => [
          'file_validate_extensions' => ['xlsx xlsm xltx xltm xls xlt ods ots slk xml gnumeric htm html csv'],
        ],
        'sheet' => [
          '#type' => 'select',
          '#title' => $this->t('Sheet'),
          '#empty_value' => '-\\_/- select -/_\\-',
          '#options' => $sheet_options,
          '#default_value' => count($sheet_options) === 1 ? key($sheet_options) : NULL,
          '#states' => [
            'visible' => [
              ':input[name="target_file[fids]"]' => [
                'filled' => TRUE,
              ],
            ],
          ],
        ],
      ],
    ];
    $form['spreadsheet']['target_file']['mappings'] = [
      '#type' => 'details',
      '#group' => 'flow',
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

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute('<front>'),
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
