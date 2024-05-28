<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Helpers for dealing with migration info in form state.
 */
trait MigrationTrait {

  /**
   * Helper; map onto a migration to derive array coordinates.
   *
   * @param string|\Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration for which to derive coordinates.
   *
   * @return string[]
   *   Where in the form state storage array the entries for the given migration
   *   should be located.
   */
  protected static function migrationEntryCoords($migration) {
    $migration_id = $migration instanceof MigrationInterface ?
      $migration->id() :
      $migration;

    return [
      'migration',
      $migration_id,
      'entries',
    ];
  }

  /**
   * Helper; check for existence of the entries.
   */
  protected static function hasEntries($migration, FormStateInterface $form_state) {
    return NestedArray::keyExists(
      $form_state->getStorage(),
      static::migrationEntryCoords($migration)
    );
  }

  /**
   * Helper; get the entries.
   */
  protected static function getEntries($migration, FormStateInterface $form_state) {
    return NestedArray::getValue(
      $form_state->getStorage(),
      static::migrationEntryCoords($migration)
    );
  }

  /**
   * Helper; set the entries.
   */
  protected static function setEntries($migration, FormStateInterface $form_state, $entries) {
    NestedArray::setValue(
      $form_state->getStorage(),
      static::migrationEntryCoords($migration),
      $entries
    );
  }

}
