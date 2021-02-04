<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Url;

use Symfony\Component\DependencyInjection\ContainerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\islandora_spreadsheet_ingest\Spreadsheet\ChunkReadFilter;

/**
 * Form for setting up ingests.
 */
class FileUpload extends EntityForm {

  protected $entityTypeManager;

  /**
   * Is entity_type.manager service for `file`.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileEntityStorage;

  protected $spreadsheetService;

  protected $migrationPluginManager;

  protected $fileUsage;

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
    $instance->fileUsage = $container->get('file.usage');
    $instance->systemFileConfig = $container->get('config.factory')->get('system.file');

    return $instance;
  }

  protected function getTargetFile(FormStateInterface $form_state) {
    $target_file = $form_state->getValue(['sheet', 'file']);
    if ($target_file) {
      return $this->fileEntityStorage->load(reset($target_file));
    }
    throw new \Exception('No target file selected.');

  }

  protected function getSpreadsheetOptions(FormStateInterface $form_state) {
    $list = $this->spreadsheetService->listWorksheets($this->getTargetFile($form_state));
    return $list ?
      $list :
      // XXX: Need to provide _some_ name for things like CSVs.
      [$this->t('Single-sheet format')];
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
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
    ];
    $form['sheet'] = [
      'file' => [
        '#type' => 'managed_file',
        '#title' => $this->t('Target file'),
        '#default_value' => $entity->getSheet()['file'],
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
      '#default_value' => $this->entity->getOriginalMapping(),
      '#options' => [
        "{$this->t('Migration Group')}" => iterator_to_array($map_to_labels($this->entityTypeManager, 'migration_group')),
        "{$this->t('Ingest Requests')}" => iterator_to_array($map_to_labels($this->entityTypeManager, 'isi_request')),
      ],
    ];

    return $form;
  }

  protected function mapMappings($mapping) {
    dsm($mapping);
    list($type, $id) = explode(':', $mapping);

    $map = [
      'isi_request' => 'getMappingFromRequest',
      'migration_group' => 'mapMappingFromMigrationGroup',
    ];

    return call_user_func([$this, $map[$type]], $id);
  }

  protected function mapMappingFromMigrationGroup($id) {
    $map_migrations = function ($etm, $mpm) use ($id) {
      $storage = $etm->getStorage('migration');
      $names = $storage->getQuery()->condition('migration_group', $id)->execute();
      $m_plus_m = $storage->loadMultiple($names);

      $migrations = $mpm->createinstances(
        $names,
        array_map(
          function ($a) { return $a->toArray(); },
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
          'mappings' => iterator_to_array($map_migration($migration))
        ];
      }
    };

    return iterator_to_array($map_migrations(
      $this->entityTypeManager,
      $this->migrationPluginManager
    ));
  }

  protected function getMappingFromRequest($id) {
    return $this->entityTypeManager->getStorage('isi_request')->load($id)->getMappings();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $request = $this->entity;

    // TODO: Copy/transform the info from the target.
    $mapped = $this->mapMappings($request->getOriginalMapping());
    dsm($mapped);
    if (!$mapped) { return ;}
    $request->set('mappings', $mapped);

    try {
      $request->save();

      $this->fileUsage->add(
        $this->getTargetFile($form_state),
        'islandora_spreadsheet_ingest',
        $request->getEntityTypeId(),
        $request->id()
      );

      $form_state->setRedirect('entity.isi_request.edit_form', [
        'isi_request' => $request->id(),
      ]);
    }
    catch (\Exception $e) {
      dsm($e);
    }
  }

  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('isi_request')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
