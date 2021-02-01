<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
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
class Mapping extends EntityForm {

  use MigrationTrait;

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

  protected function getTargetFile() {
    $target_file = $this->entity->getSheet()['file'];
    if ($target_file) {
      return $this->fileEntityStorage->load(reset($target_file));
    }
    throw new \Exception('No target file selected.');

  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['#entity_builders'] = [
      '::mapMappings',
    ];

    $form['mappings'] = [
      '#type' => 'islandora_spreadsheet_ingest_migration_mappings',
      '#request' => $this->entity,
      '#input' => FALSE,
    ];

    return $form;
  }

  protected function mapMappings($entity_type, $entity, &$form, FormStateInterface &$form_state) {
    dsm($entity->getMappings(), 'qwer');

    $map_mapping = function ($info) use ($form_state) {
      foreach ($info['entries'] as $key => $process) {
        yield $key => [
          'pipeline' => $process->toPipelineArray(),
        ];
      }
    };

    $map_migrations = function () use ($form_state, $map_mapping) {
      foreach ($form_state->get('migration') as $name => $info) {
        $mappings = iterator_to_array($map_mapping($info));
        $table = $form_state->getValue('mappings')[$name]['table'];
        uksort($mappings, function ($a, $b) use ($table) {
          return $table[$a]['weight'] - $table[$b]['weight'];
        });
        yield $name => [
          'original_migration_id' => $name,
          'mappings' => $mappings,
        ];
      }
    };

    $entity->set('mappings', iterator_to_array($map_migrations()));
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    dsm($this->entity);

    $form_state->setRedirect('islandora_spreadsheet_ingest.request.review', [
      'isi_request' => $this->entity->id(),
    ]);

    return $result;
  }

}
