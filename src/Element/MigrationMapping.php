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
 * @FormElement("islandora_spreadsheet_ingest_migration_mapping")
 */
class MigrationMapping extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#tree' => TRUE,
      '#source' => [],
      '#migration' => NULL,
      '#process' => [
        [static::class, 'processMapping'],
        [static::class, 'processEntries'],
      ],
      '#entries_prepopulated' => FALSE,
    ];
  }

  protected static function getDestinationProperties(MigrationInterface $migration) {
    $row_probe = new Row();
    $dp = $migration->getDestinationPlugin();
    if ($dp instanceof Entity) {
      $key = $dp->getPluginId();
      $bundle = $dp->getBundle($row_probe);
      $def = \Drupal::typedDataManager()->createDataDefinition($bundle ? "$key:$bundle" : $key);
      $migration_options = [];
      foreach ($def->getPropertyDefinitions() as $prop) {
        $migration_options[$prop->getName()] = $prop;
      }
      return $migration_options;
    }
    else {
      throw new \Exception('What are you trying to map to!?');
    }
  }

  protected static function getDestinationOptions(MigrationInterface $migration) {
    return array_map(function ($prop) {
      return t(':ind:label (:name)', [
        ':label' => $prop->getLabel(),
        ':name' => $prop->getName(),
        ':ind' => $prop->isRequired() ? '*' : '',
      ]);
    }, static::getDestinationProperties($migration));
  }

  protected static function getSourceOptions(FileInterface $file, $sheet) {
    $header = \Drupal::service('islandora_spreadsheet_ingest.spreadsheet_service')->getHeader($file, $sheet);
    return array_combine($header, $header);
  }

  public static function processMapping(array &$element, FormStateInterface $form_state) {
    $element['table'] = [
      '#type' => 'table',
      '#caption' => $element['#migration']->label(),
      '#header' => [
        t('Source'),
        t('Destination'),
        ['data' => t('Weight'), 'class' => ['tabledrag-hide']],
      ],
      '#tableselect' => TRUE,
      '#empty' => t('It be empty, yo.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'group-weight',
        ],
      ],
    ];
    $element['remove_selected'] = [
      '#type' => 'submit',
      '#value' => t('Remove selected'),
      '#name' => "remove_{$element['#migration']->id()}",
      '#submit' => [
        [static::class, 'submitRemoveMapping'],
      ],
    ];
    $element['add_mapping'] = [
      'source_column' => [
        '#type' => 'select',
        '#title' => t('Source Columns'),
        '#options' => static::getSourceOptions($element['#source']['file'], $element['#source']['sheet']),
      ],
      'destination' => [
        '#type' => 'select',
        '#title' => t('Destination'),
        '#options' => static::getDestinationOptions($element['#migration']),
      ],
      'add_new_mapping' => [
        '#type' => 'submit',
        '#value' => t('Add mapping'),
        '#name' => "add_{$element['#migration']->id()}",
        '#submit' => [
          [static::class, 'submitAddMapping'],
        ]
      ],
    ];
    return $element;
  }

  public static function processEntries(array &$element, FormStateInterface $form_state) {
    if (!$element['#entries_prepopulated']) {
      // TODO: Prepopulate the entries... to form state storage?
      $element['#entries_prepopulated'] = TRUE;
    }
    // TODO: Generate table entries from storage.
    $element['table'] += [
      1 => [
        '#attributes' => ['class' => ['draggable']],
        'source' => ['#markup' => t('what')],
        'destination' => ['#markup' => t('there')],
        'weight' => [
           '#type' => 'weight',
           '#default_value' => 1,
           '#wrapper_attributes' => [
             'class' => ['tabledrag-hide'],
           ],
         ],
      ],
      2 => [
        '#attributes' => ['class' => ['draggable']],
        'source' => ['#markup' => t('what')],
        'destination' => ['#markup' => t('there')],
        'weight' => [
          '#type' => 'weight',
          '#default_value' => 2,
          '#wrapper_attributes' => [
            'class' => ['tabledrag-hide'],
          ],
        ],
      ],
    ];
    return $element;
  }

  public static function submitAddMapping(array $form, FormStateInterface $form_state) {
    // TODO: Add the new entry to the form state and rebuild.
    $form_state->setRebuild();
    throw new Exception("Not implemented");
  }
  public static function submitRemoveMapping(array $form, FormStateInterface $form_state) {
    // TODO: Remove the selected entries from the form state and rebuild.
    $form_state->setRebuild();
    throw new Exception('Not implemented');
  }

}
