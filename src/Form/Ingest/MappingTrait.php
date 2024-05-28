<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Migration re-mapping helper.
 */
trait MappingTrait {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Migration plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $migrationPluginManager;

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
    [$type, $id] = explode(':', $mapping);

    $map = [
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
      $names = array_keys(array_filter($mpm->getDefinitions(), function (array $def) use ($id) {
        return ($def['migration_group'] ?? '') === $id;
      }));

      if (empty($names)) {
        // XXX: Avoids behaviour of ::createInstances() when passed an empty
        // array, where it would load _all_ instances.
        // XXX: Bad sniff is bad, and does not detect this being inside of a
        // generator.
        // phpcs:ignore Drupal.Commenting.FunctionComment.InvalidReturnNotVoid
        return;
      }

      $migrations = $mpm->createInstances($names);

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

}
