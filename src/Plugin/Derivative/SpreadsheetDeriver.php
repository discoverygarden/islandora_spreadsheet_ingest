<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\Derivative;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

use Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface;

/**
 * Expose spreadsheet migrations as derivative plugins.
 */
class SpreadsheetDeriver extends DeriverBase implements ContainerDeriverInterface {

  protected $entityTypeManager;
  protected $migrationGroupDeriver;
  protected $requestStorage;
  protected $migrationStorage;

  /**
   * Constructor.
   */
  public function __construct(
    $base_plugin_id,
    EntityTypeManagerInterface $entity_type_manager,
    MigrationGroupDeriverInterface $migration_group_deriver
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->migrationGroupDeriver = $migration_group_deriver;
    $this->requestStorage = $this->entityTypeManager->getStorage('isi_request');
    $this->migrationStorage = $this->entityTypeManager->getStorage('migration');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager'),
      $container->get('islandora_spreadsheet_ingest.migration_group_deriver')
    );
  }

  protected function getUsedColumns(array $mappings) {
    foreach ($mappings as $field => $info) {
      foreach ($info['pipeline'] as $process_step) {
        if (!isset($process_step['source'])) {
          continue;
        }

        $sources = (array) $process_step['source'];

        foreach ($sources as $source) {
          if (strpos($source, '@') !== 0) {
            yield $source;
          }
        }
      }
    }
  }

  protected function mapDependencies($migration, $new_mg) {
    $original_deps = $migration->get('migration_dependencies') ?? [];
    $deps = [];

    foreach ($original_deps as $type => $mig_deps) {
      $_deps = [];

      foreach ($mig_deps as $mig_dep) {
        $target = $this->migrationStorage->load($mig_dep);
        if ($target->get('migration_group') === $migration->get('migration_group')) {
          $_deps[] = "{$new_mg}:{$mig_dep}";
        }
      }

      $deps[$type] = $_deps;
    }

    return $deps;
  }

  protected function mapStepMigrations($step) {
    $plugin = $step['plugin'] ?? 'get';
    if ($plugin == 'migration_lookup') {
      // TODO: Do the mapping.
    }
  }

  protected function mapPipelineMigrations($pipelines, $) {
    foreach ($pipelines as $name => $steps) {
      yield $name => array_map([$this, 'mapStepMigrations'], $steps);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getStorage('isi_request')->loadMultiple() as $id => $request) {
      $mg_name = $this->migrationGroupDeriver->deriveName($request);

      if (!$request->status() || !$request->getActive()) {
        continue;
      }

      assert($this->entityTypeManager->getStorage('migration_group')->load($mg_name));

      foreach ($this->request->getMappings() as $name => $info) {
        $original_migration = $this->migrationStorage->load($info['original_migration_id']);
        $derived_name = "{$mg_name}:{$name}";
        $this->derivatives[$derived_name] = [
          'id' => $derived_name,
          'label' => $name,
          'migration_group' => $mg_name,
          'source' => [
            'columns' => iterator_to_array($this->getUsedColumns($info['mappings'])),
          ],
          'process' => array_column($info['mappings'], 'pipeline'),
          'destination' => $original_migration->get('destination'),
          'dependencies' => array_merge_recursive(
            $original_migration->get('dependencies'),
            [
              'enforced' => [
                $request->getConfigDependencyKey() => [
                  $request->getConfigDependencyName(),
                ],
              ],
            ],
          ),
          'migration_dependencies' => $this->mapDependencies($original_migration, $mg_name),
        ];
      }

    }

    return $this->derivatives;
  }

}
