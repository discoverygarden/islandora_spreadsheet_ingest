<?php

namespace Drupal\islandora_spreadsheet_ingest\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\Html as HtmlUtility;

use Drupal\migrate\Row;
use Drupal\file\FileInterface;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\islandora_spreadsheet_ingest\Model\RowSource;
use Drupal\islandora_spreadsheet_ingest\Model\Pipeline;
use Drupal\islandora_spreadsheet_ingest\Model\ProcessPluginWrapper;
use Drupal\islandora_spreadsheet_ingest\Model\ProcessSourcePluginWrapper;

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
        [static::class, 'prepopulateEntries'],
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

  protected static function getDestinationOptions(MigrationInterface $migration, FormStateInterface $form_state) {
    return array_map(
      function ($prop) {
        return t(':ind:label (:name)', [
          ':label' => $prop->getLabel(),
          ':name' => $prop->getName(),
          ':ind' => $prop->isRequired() ? '*' : '',
        ]);
      },
      static::getUnusedDestinationProperties($migration, $form_state)
    );
  }

  protected static function getSourceOptions(array $source) {
    $header = \Drupal::service('islandora_spreadsheet_ingest.spreadsheet_service')->getHeader($source['file'], $source['sheet']);
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
        '#options' => static::getSourceOptions($element['#source']),
      ],
      'destination' => [
        '#type' => 'select',
        '#title' => t('Destination'),
        '#options' => static::getDestinationOptions($element['#migration'], $form_state),
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

  protected static function getEntries(MigrationInterface $migration, FormStateInterface $form_state) {
    return $form_state->getStorage()[$migration->id()]['entries'] ?: [];
  }
  protected static function setEntries(MigrationInterface $migration, FormStateInterface $form_state, $entries) {
    $storage =& $form_state->getStorage();
    $storage[$migration->id()]['entries'] = $entries;
  }

  protected static function getUnusedDestinationProperties(MigrationInterface $migration, FormStateInterface $form_state) {

    return array_diff_key(
      static::getDestinationProperties($migration),
      static::getEntries($migration, $form_state)
    );

  }

  protected static function mapMigrationProcessToPipelines(MigrationInterface $migration) {
    $entries = [];

    $map_to_source = function ($config) use (&$entries) {
      if (isset($config['source']) && ($source = $config['source']) && is_string($source)) {
        return isset($entries[$source]) ? $entries[$source] : new RowSource($source);
      }
      else {
        return new ProcessSourcePluginWrapper($config);
      }
    };

    foreach ($migration->getProcess() as $prop_name => $configs) {
      $entry = new Pipeline(
        $map_to_source($configs[0]),
        $prop_name
      );
      foreach (array_slice($configs, 1) as $config) {
        $entry->addStep(new ProcessPluginWrapper($config));
      }
      $entries[$prop_name] = $entry;
    }

    return $entries;
  }

  public static function prepopulateEntries(array &$element, FormStateInterface $form_state) {
    //if (!$element['#entries_prepopulated']) {
      dsm('reloading...');
      // Load up the entries from the migration.
      $entries = static::mapMigrationProcessToPipelines($element['#migration']);
      static::setEntries($element['#migration'], $form_state, $entries);

      $unused_props_in_source = array_intersect_key(
        static::getUnusedDestinationProperties($element['#migration'], $form_state),
        static::getSourceOptions($element['#source'])
      );

      foreach ($unused_props_in_source as $name => $unused) {
        $entries[$name] = new Pipeline(
          new RowSource($name),
          $name
        );
      }
      static::setEntries($element['#migration'], $form_state, $entries);
      $element['#entries_prepopulated'] = TRUE;
    //}

    return $element;
  }

  public static function processEntries(array &$element, FormStateInterface $form_state) {

    // Generate table entries from storage.
    $element['table'] += array_map(
      function ($entry) {
        return [
          '#type' => 'islandora_spreadsheet_ingest_migration_mapping_entry',
          '#entry' => $entry,
        ];
      },
      static::getEntries($element['#migration'], $form_state)
    );

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
