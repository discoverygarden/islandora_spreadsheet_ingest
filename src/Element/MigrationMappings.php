<?php

namespace Drupal\islandora_spreadsheet_ingest\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\Html as HtmlUtility;

use Drupal\migrate\MigrationInterface;

/**
 * Migration mappings wrapper element.
 *
 * @FormElement("islandora_spreadsheet_ingest_migration_mappings")
 */
class MigrationMappings extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#tree' => TRUE,
      '#source' => [],
      '#migration_group' => '',
      '#process' => [
        [static::class, 'processMigrationGroup'],
        [static::class, 'processMigrations'],
      ],
    ];
  }

  public static function processMigrationGroup(array &$element, FormStateInterface $form_state) {
    $migration_storage = \Drupal::entityTypeManager()->getStorage('migration');
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');

    $names = $migration_storage->getQuery()->condition('migration_group', $element['#migration_group'])->execute();
    $migration_plus_migrations = $migration_storage->loadMultiple($names);

    $element['#migrations'] = $migration_plugin_manager->createInstances(
      $names,
      array_map(
        function ($a) { return $a->toArray(); },
        $migration_plus_migrations
      )
    );

    // XXX: Debug.....
    $element['#migrations'] = array_slice($element['#migrations'], 0, 1);

    return $element;
  }

  public static function processMigrations(array &$element, FormStateInterface $form_state) {
    foreach ($element['#migrations'] as $name => $migration) {
      $element[$name] = [
        '#type' => 'islandora_spreadsheet_ingest_migration_mapping',
        '#source' => $element['#source'],
        '#migration' => $migration,
      ];
    }
    return $element;
  }

}
