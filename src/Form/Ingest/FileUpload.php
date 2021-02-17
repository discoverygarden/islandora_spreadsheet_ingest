<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\NestedArray;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for setting up ingests.
 */
class FileUpload extends EntityForm {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * Migration plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Drupal's system.file config.
   *
   * @var \Drupal\Core\Config\ConfigBase
   */
  protected $systemFileConfig;

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

    return $instance;
  }

  /**
   * Helper; load the target file.
   */
  protected function getTargetFile(FormStateInterface $form_state) {
    $target_file = $form_state->getValue(['sheet', 'file']);
    if (!$target_file) {
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
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
        '#default_value' => $form_state->getValue(['sheet', 'file'], $entity->getSheet()['file']),
        '#upload_validators' => [
          'file_validate_extensions' => ['xlsx xlsm xltx xltm xls xlt ods ots slk xml gnumeric htm html csv'],
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
        '#default_value' => $form_state->getValue(['sheet', 'sheet'], $entity->getSheet()['sheet']),
        '#states' => [
          'visible' => [
            ':input[name="sheet[file][fids]"]' => [
              'filled' => TRUE,
            ],
          ],
        ],
      ],
    ];
    $map_to_labels = function (EntityTypeManagerInterface $etm, $type) {
      foreach ($etm->getStorage($type)->loadMultiple() as $id => $entity) {
        yield "$type:$id" => $entity->label();
      }
    };
    $form['originalMapping'] = [
      '#type' => 'select',
      '#title' => $this->t('Base mapping'),
      '#description' => $this->t('The mapping upon which this ingest will be based.'),
      '#default_value' => $entity->getOriginalMapping(),
      '#disabled' => !$entity->isNew(),
      '#options' => [
        "{$this->t('Migration Group')}" => iterator_to_array($map_to_labels($this->entityTypeManager, 'migration_group')),
        "{$this->t('Ingest Requests')}" => iterator_to_array($map_to_labels($this->entityTypeManager, 'isi_request')),
      ],
    ];

    return $form;
  }

  /**
   * Map according to the type of mapping requested.
   *
   * @param string $mapping
   *   A type-namespaced identifier for the source mapping.
   *
   * @return array
   *   The mapped mapping.
   */
  protected function mapMappings($mapping) {
    list($type, $id) = explode(':', $mapping);

    $map = [
      'isi_request' => 'getMappingFromRequest',
      'migration_group' => 'mapMappingFromMigrationGroup',
    ];

    return call_user_func([$this, $map[$type]], $id);
  }

  /**
   * Derive mapping from a given migration group.
   *
   * @param string $id
   *   The id/name of the migration group from which to derive a mapping.
   *
   * @return array
   *   The mapped mapping.
   */
  protected function mapMappingFromMigrationGroup($id) {
    $map_migrations = function ($etm, $mpm) use ($id) {
      $storage = $etm->getStorage('migration');
      $names = $storage->getQuery()->condition('migration_group', $id)->execute();

      $migrations = $mpm->createinstances(
        $names,
        array_map(
          function ($a) {
            return $a->toArray();
          },
          $storage->loadMultiple($names)
        )
      );

      $start = 0;
      $map_migration = function ($migration) use (&$start) {
        foreach ($migration->getProcess() as $prop => $configs) {
          yield $prop => [
            'weight' => $start++,
            'pipeline' => $configs,
          ];
        }
      };

      foreach ($migrations as $mid => $migration) {
        yield $mid => [
          'original_migration_id' => $mid,
          'mappings' => iterator_to_array($map_migration($migration)),
        ];
      }
    };

    return [
      "migration_group:{$id}",
      iterator_to_array($map_migrations(
        $this->entityTypeManager,
        $this->migrationPluginManager
      )),
    ];
  }

  /**
   * Copy the mapping from another request.
   *
   * @param string $id
   *   The other request from which to copy the mapping.
   *
   * @return array
   *   The mapping to assign.
   */
  protected function getMappingFromRequest($id) {
    $original = $this->entityTypeManager->getStorage('isi_request')->load($id);
    return [$original->getOriginalMapping(), $original->getMappings()];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $request = $this->entity;

    // Copy/transform the info from the target.
    list($original, $mapped) = $this->mapMappings($request->getOriginalMapping());
    $request->set('mappings', $mapped);
    $request->set('originalMapping', $original);

    try {
      $request->save();

      $form_state->setRedirect('entity.isi_request.map_form', [
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
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
