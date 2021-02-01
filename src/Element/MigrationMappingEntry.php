<?php

namespace Drupal\islandora_spreadsheet_ingest\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\Html as HtmlUtility;

use Drupal\migrate\Row;
use Drupal\file\FileInterface;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Migration mapping element.
 *
 * @FormElement("islandora_spreadsheet_ingest_migration_mapping_entry")
 */
class MigrationMappingEntry extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#attributes' => [
        'class' => [
          'draggable',
        ],
      ],
      '#process' => [
        [static::class, 'processEntry'],
      ],
      '#entry' => NULL,
      '#weight' => 0,
    ];
  }

  public static function processEntry(array &$element, FormStateInterface $form_state) {
    assert($element['#entry'] !== NULL);

    $element['select'] = [
      '#type' => 'checkbox',
      '#return_value' => 1,
      '#wrapper_attributes' => [
        'class' => [
          'table-select',
        ],
      ],
    ];
    $source_name = $element['#entry']->getSource()->getName();
    $element['source'] = [
      '#markup' => is_array($source_name) ?
        ('[' . implode(', ', $source_name) . ']') :
        $source_name,
    ];
    $element['destination'] = [
      '#markup' => $element['#entry']->getDestinationName(),
    ];
    $element['weight'] = [
      '#type' => 'weight',
      '#default_value' => $element['#weight'],
      '#wrapper_attributes' => [
        'class' => [
          'tabledrag-hide',
        ],
      ],
    ];

    return $element;
  }

}
