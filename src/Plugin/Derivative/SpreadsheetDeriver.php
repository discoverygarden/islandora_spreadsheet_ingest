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
    $mapping = [
      'get' => function ($step) { yield from (array) ($step['source'] ?? []); },
      'migration_lookup' => function ($step) {
        if (isset($step['source'])) {
          yield from (array) ($step['source'] ?? []);
        }
        if (isset($step['source_ids'])) {
          foreach ($step['source_ids'] as $ids) {
            yield from $ids;
          }
        }
      },
    ];

    foreach ($mappings as $field => $info) {
      foreach ($info['pipeline'] as $process_step) {
        $plugin = $process_step['plugin'] ?? 'get';
        $mapper = $mapping[$plugin] ?? $mapping['get'];

        foreach ($mapper($process_step) as $source) {
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
          $_deps[] = $this->deriveMigrationName($new_mg, $mig_dep);
        }
      }

      $deps[$type] = $_deps;
    }

    return $deps;
  }

  protected function deriveMigrationName($mg_name, $target) {
    return "{$mg_name}:{$target}";
  }

  protected function sameMigrationGroup($mig, $target) {
    $loaded_target = $this->migrationStorage->load($target);
    $mg = $loaded_target->get('migration_group');
    return $mg && $mg == $mig->get('migration_group');
  }

  protected function mapStepMigrations($steps, $mig, $mg_name) {
    foreach ($steps as $step) {
      $plugin = $step['plugin'] ?? 'get';
      if ($plugin == 'migration_lookup') {
        // Do the mapping.
        if (is_array($step['migration'])) {
          // Map the listed migrations, and any similar references under
          // "source_ids".
          foreach ($step['migration'] as &$mig_step) {
            if ($this->sameMigrationGroup($mig, $mig_step)) {
              $old_name = $mig_step;
              $mig_step = $this->deriveMigrationName($mg_name, $mig_step);
              $step['source_ids'][$mig_step] = $step['source_ids'][$old_name];
              unset($step['source_ids'][$old_name]);
            }
          }
          unset($mig_step);
        }
        elseif (is_string($step['migration'])) {
          // Just map the single migration.
          if ($this->sameMigrationGroup($mig, $step['migration'])) {
            $step['migration'] = $this->deriveMigrationName($mg_name, $step['migration']);
          }
        }
      }
      yield $step;
    }
  }

  protected function mapPipelineMigrations($processes, $mig, $mg_name) {
    foreach ($processes as $name => $info) {
      yield $name => iterator_to_array($this->mapStepMigrations($info['pipeline'], $mig, $mg_name));
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

      foreach ($request->getMappings() as $name => $info) {
        $original_migration = $this->migrationStorage->load($info['original_migration_id']);
        $derived_name = $this->deriveMigrationName($mg_name, $name);
        $this->derivatives[$derived_name] = [
          'id' => $derived_name,
          'label' => $name,
          'migration_group' => $mg_name,
          'source' => [
            'columns' => array_unique(iterator_to_array($this->getUsedColumns($info['mappings']))),
          ],
          'process' => iterator_to_array(
            $this->mapPipelineMigrations(
              $info['mappings'],
              $original_migration,
              $mg_name
            )
          ),
          'destination' => $original_migration->get('destination'),
          'dependencies' => array_merge_recursive(
            $original_migration->get('dependencies'),
            [
              'enforced' => [
                $request->getConfigDependencyKey() => [
                  $request->getConfigDependencyName(),
                ],
              ],
            ]
          ),
          'migration_dependencies' => $this->mapDependencies($original_migration, $mg_name),
        ];
      }

    }

    dsm($this->derivatives, 'asdf');

    return $this->derivatives;
  }

}
