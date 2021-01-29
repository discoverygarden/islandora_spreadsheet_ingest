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
      '#request' => NULL,
      '#process' => [
        [static::class, 'processMigrations'],
      ],
    ];
  }

  protected static function processMapping($request) {
    foreach ($request->getMappings() as $name => $configs) {
      yield $name => [
        '#type' => 'islandora_spreadsheet_ingest_migration_mapping',
        '#request' => $request,
        '#original_migration' => $name,
      ];
      // XXX: Debug, only throw in the one for now.
      break;
    }
  }

  public static function processMigrations(array &$element, FormStateInterface $form_state) {
    return $element + iterator_to_array(call_user_func([static::class, 'processMapping'], $element['#request']));
  }

}
