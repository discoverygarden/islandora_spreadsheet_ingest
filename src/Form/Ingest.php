<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Url;

use Symfony\Component\DependencyInjection\ContainerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\islandora_spreadsheet_ingest\Spreadsheet\ChunkReadFilter;

/**
 * Form for setting up ingests.
 */
class Ingest extends FormBase {

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
          '#options' => $this->getHeader($form_state),
        ],
        'destination' => [
          '#type' => 'select',
          '#title' => $this->t('Destination'),
          '#options' => [],
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
