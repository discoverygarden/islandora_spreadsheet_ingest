<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

/**
 * Migration pipeline process plugin representation.
 */
interface PipelineStepInterface {

  /**
   * Get the migration process plugin definition for the given step.
   *
   * @return array
   *   An array representing a migration process plugin.
   */
  public function toProcessArray();

}
