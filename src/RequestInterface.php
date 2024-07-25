<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Request content entity interface.
 */
interface RequestInterface extends ConfigEntityInterface {

  /**
   * Get the sheet to process.
   *
   * @return array
   *   An associative array containing:
   *   - file: An array of file IDs.
   *   - sheet: A string indicating which sheet of the sheet should be used. May
   *     be the empty string for things for which it is not relevent, like CSV.
   */
  public function getSheet();

  /**
   * Get the migration mappings.
   *
   * @return array
   *   An associative array mapping migration names to associative arrays
   *   containing:
   *   - original_migration_id: A string indicating the name of the original
   *     migration.
   *   - mappings: An associative array mapping destination field names to
   *     associative arrays containing:
   *     - pipeline: An array of migration process plugin definitions to be
   *       executed to produce the given field.
   */
  public function getMappings();

  /**
   * Get the status of the given item, whether or not it is active.
   *
   * @return bool
   *   TRUE if active; otherwise, FALSE.
   */
  public function getActive();

}
