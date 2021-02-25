<?php

namespace Drupal\islandora_spreadsheet_ingest\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

use Drupal\islandora_spreadsheet_ingest\RequestInterface;

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

  /**
   * Helper, generate the a "mapping" entry for each mapping in the request.
   */
  protected static function processMapping(RequestInterface $request) {
    foreach ($request->getMappings() as $name => $configs) {
      yield $name => [
        '#type' => 'islandora_spreadsheet_ingest_migration_mapping',
        '#request' => $request,
        '#original_migration' => $name,
      ];
    }
  }

  /**
   * Process callback; expand the list to multiple "mapping" entries.
   */
  public static function processMigrations(array &$element, FormStateInterface $form_state) {
    return $element + iterator_to_array(
      call_user_func(
        [static::class, 'processMapping'],
        $element['#request']
      )
    );
  }

}
