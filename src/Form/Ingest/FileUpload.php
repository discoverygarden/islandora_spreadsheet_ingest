<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora_spreadsheet_ingest\RequestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for setting up ingests.
 */
class FileUpload extends EntityForm {

  use MappingTrait;

  /**
   * File storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileEntityStorage;

  /**
   * Spreadsheet service.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\Spreadsheet\SpreadsheetServiceInterface
   */
  protected $spreadsheetService;

  /**
   * Drupal's system.file config.
   *
   * @var \Drupal\Core\Config\ConfigBase
   */
  protected $systemFileConfig;

  /**
   * The migration deriver.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\MigrationDeriverInterface
   */
  protected $migrationDeriver;

  /**
   * The migration group deriver.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface
   */
  protected $migrationGroupDeriver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileEntityStorage = $instance->entityTypeManager->getStorage('file');
    $instance->spreadsheetService = $container->get('islandora_spreadsheet_ingest.spreadsheet_service');
    $instance->migrationPluginManager = $container->get('plugin.manager.migration');
    $instance->systemFileConfig = $container->get('config.factory')->get('system.file');
    $instance->migrationDeriver = $container->get('islandora_spreadsheet_ingest.migration_deriver');
    $instance->migrationGroupDeriver = $container->get('islandora_spreadsheet_ingest.migration_group_deriver');

    return $instance;
  }

  /**
   * Helper; load the target file.
   */
  protected function getTargetFile(FormStateInterface $form_state) {
    $target_file = $form_state->getValue(['sheet', 'file']);
    if (isset($target_file['fids'])) {
      $target_file = $target_file['fids'];
    }
    elseif (!$target_file) {
      $target_file = $this->entity->getSheet()['file'] ?? FALSE;
    }

    if ($target_file) {
      return $this->fileEntityStorage->load(reset($target_file));
    }
    throw new \Exception('No target file selected.');

  }

  /**
   * Helper; get the available options.
   */
  protected function getSpreadsheetOptions(FormStateInterface $form_state) {
    $file = $this->getTargetFile($form_state);
    if (!$file) {
      $form_state->setValue(['sheet', 'sheet'], '');
    }
    $list = $file ?
      $this->spreadsheetService->listWorksheets($file) :
      NULL;
    // XXX: Need to provide _some_ name for things like CSVs.
    return $list ?? [$this->t('Single-sheet format')];
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if ($actions['submit']) {
      $actions['submit']['#validate'] = array_merge(
        $actions['submit']['#validate'] ?? [],
        [
          '::validateForm',
        ]
      );
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    try {
      $file = $this->getTargetFile($form_state);
      $sheets = $file ?
        $this->spreadsheetService->listWorksheets($file) :
        FALSE;

      $coords = ['sheet', 'sheet'];

      $entered = $form_state->getValue($coords);

      if ($sheets === NULL) {
        // No sheets in the given file, don't really care about the "entered"
        // value.
      }
      elseif (!in_array($entered, $sheets)) {
        $form_state->setError(NestedArray::getValue($form, $coords), $this->t('The targeted sheet "%sheet" does not appear to exist. Valid sheets: %sheets', [
          '%sheet' => $entered,
          '%sheets' => implode(', ', $sheets),
        ]));
      }
    }
    catch (\Exception $e) {
      $form_state->setError(NestedArray::getValue($form, ['sheet', 'file']), $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $entity = $this->entity;

    try {
      $sheets = $this->getSpreadsheetOptions($form_state);
    }
    catch (\Exception $e) {
      $sheets = [];
    }

    $form['#tree'] = TRUE;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t("Label for the Example."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#disabled' => !$entity->isNew(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
    ];
    $form['sheet'] = [
      'file' => [
        '#type' => 'managed_file',
        '#title' => $this->t('Target file'),
        '#default_value' => $form_state->getValue(['sheet', 'file', 'fids'], $entity->getSheet()['file'] ?? NULL),
        '#required' => TRUE,
        '#upload_validators' => [
          'file_validate_extensions' => ['xlsx ods csv'],
        ],
        '#upload_location' => "{$this->systemFileConfig->get('default_scheme')}://",
        'sheets' => [
          '#type' => 'item_list',
          '#title' => $this->t('Sheets contained'),
          '#access' => !empty($sheets),
          '#items' => $sheets,
        ],
      ],
      'sheet' => [
        '#type' => 'textfield',
        '#title' => $this->t('Sheet'),
        '#description' => $this->t('The name of the worksheet. Leave empty for single-sheet formats such as CSV.'),
        '#default_value' => $form_state->getValue(['sheet', 'sheet'], $entity->getSheet()['sheet'] ?? NULL),
        '#states' => [
          'visible' => [
            ':input[name="sheet[file][fids]"]' => [
              'filled' => TRUE,
            ],
          ],
        ],
      ],
    ];
    $map_to_labels = function (EntityTypeManagerInterface $etm, $type, $filter = NULL) {
      foreach ($etm->getStorage($type)->loadMultiple() as $id => $entity) {
        if ($filter === NULL || $filter($entity)) {
          yield "$type:$id" => $entity->label();
        }
      }
    };
    $form['originalMapping'] = [
      '#type' => 'select',
      '#title' => $this->t('Base mapping'),
      '#description' => $this->t('The mapping upon which this ingest will be based.'),
      '#default_value' => $entity->getOriginalMapping(),
      '#disabled' => !$entity->isNew(),
      '#options' => array_filter([
        "{$this->t('Migration Group')}" => iterator_to_array($map_to_labels($this->entityTypeManager, 'migration_group', function ($group) {
          // XXX: Ideally, we could pass conditions or whatever to an entity
          // query; however, it fails due to "migration_tags" being an
          // array...
          $config = $group->get('shared_configuration');
          return !empty($config['migration_tags']) &&
            in_array('isi_template', $config['migration_tags']) &&
            !in_array('isi_derived_migration', $config['migration_tags']);
        })),
      ]),
    ];
    $form['#entity_builders'] = [
      [$this, 'builder'],
    ];

    return $form;
  }

  /**
   * Entity building callback.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The entity being build.
   * @param array $form
   *   A reference to the form being used to build the entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state while building.
   */
  protected function builder($entity_type_id, RequestInterface $request, array &$form, FormStateInterface &$form_state) {
    // Copy/transform the info from the target.
    [$original, $mapped] = $this->mapMappings($request->getOriginalMapping());
    $request->set('mappings', $mapped);
    $request->set('originalMapping', $original);
    $request->set('sheet', [
      'file' => ($form_state->getValue(['sheet', 'file', 'fids']) ??
        $form_state->getValue(['sheet', 'file'])),
      'sheet' => $form_state->getValue(['sheet', 'sheet']),
    ]);
    $request->set('owner', $this->currentUser()->id());
    $request->set('active', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $request = $this->entity;
    try {
      $request->save();
      if ($request->getActive()) {
        $this->migrationGroupDeriver->create($request);
        $this->migrationDeriver->createAll($request);
      }
      $form_state->setRedirect('entity.isi_request.canonical', [
        'isi_request' => $request->id(),
      ]);
    }
    catch (\Exception $e) {
      $this->logger('isi.request.file_upload')->error('Exception when saving: {exc}', ['exc' => $e]);
    }
  }

  /**
   * Machine name callback to test for existence.
   *
   * @param string $id
   *   The name for which to test.
   *
   * @return bool
   *   TRUE if an item with the ID already exists; otherwise, FALSE.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('isi_request')->getQuery()
      // XXX: Strictly, this _could_ allow the leak of information regarding the
      // existence of other requests; however, unsure if we are particularly
      // concerned.
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
