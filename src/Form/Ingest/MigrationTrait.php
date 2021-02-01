<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Core\Form\FormStateInterface;

trait MigrationTrait {

  protected static function getMigrationStorage($migration, FormStateInterface $form_state) {
    return $form_state->getStorage()['migration'][$migration_id] ?: [
      'autopopulated' => FALSE,
      'entries' => [],
    ];
  }
  protected static function hasEntries($migration, FormStateInterface $form_state) {
    $migration_id = $migration instanceof MigrationInterface ?
      $migration->id() :
      $migration;
    return isset($form_state->getStorage()['migration'][$migration_id]['entries']);
  }
  protected static function getEntries($migration, FormStateInterface $form_state) {
    $migration_id = $migration instanceof MigrationInterface ?
      $migration->id() :
      $migration;
    return $form_state->getStorage()['migration'][$migration_id]['entries'];
  }
  protected static function setEntries($migration, FormStateInterface $form_state, $entries) {
    $storage =& $form_state->getStorage();
    $migration_id = $migration instanceof MigrationInterface ?
      $migration->id() :
      $migration;
    $storage['migration'][$migration_id]['entries'] = $entries;
  }

}
