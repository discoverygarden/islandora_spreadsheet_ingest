<?php

namespace Drupal\islandora_spreadsheet_ingest\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\Component\Utility\NestedArray;

use Drupal\migrate\Row;
use Drupal\file\FileInterface;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\islandora_spreadsheet_ingest\Model\RowSource;
use Drupal\islandora_spreadsheet_ingest\Model\SourceInterface;
use Drupal\islandora_spreadsheet_ingest\Model\Pipeline;
use Drupal\islandora_spreadsheet_ingest\Model\ProcessPluginWrapper;
use Drupal\islandora_spreadsheet_ingest\Model\ProcessSourcePluginWrapper;
use Drupal\islandora_spreadsheet_ingest\Model\DefaultValueSourcePropertyCreator;

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
      '#input' => FALSE,
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

  protected static function getSourceProperties(array $element, FormStateInterface $form_state) {
    $source = $element['#source'];
    $header = \Drupal::service('islandora_spreadsheet_ingest.spreadsheet_service')->getHeader($source['file'], $source['sheet']);

    return array_merge(
      array_combine($header, array_map(function ($col) {
        return new RowSource($col, t('Selected spreadsheet'));
      }, $header)),
      static::getEntries($element['#migration'], $form_state),
      [
        DefaultValueSourcePropertyCreator::NAME => new DefaultValueSourcePropertyCreator(),
      ]
    );
  }

  public static function tableSelection(array &$element, $input, FormStateInterface $form_state) {
    if ($input) {
      $keys = array_keys(array_filter($input, function ($row) {
        return isset($row['select']) ? $row['select'] : FALSE;
      }));
      return array_combine($keys, $keys);

    }
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
      '#empty' => t('There are no fields mapped.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'group-weight',
        ],
      ],
      '#value_callback' => [static::class, 'tableSelection'],
    ];
    $element['remove_selected'] = [
      '#type' => 'submit',
      '#value' => t('Remove selected'),
      '#name' => "remove_{$element['#migration']->id()}",
      '#limit_validation_errors' => [
        array_merge($element['#parents'], ['table']),
      ],
      '#validate' => [
        [static::class, 'validateRemoveMapping'],
      ],
      '#submit' => [
        [static::class, 'submitRemoveMapping'],
      ],
    ];
    $element['add_mapping'] = [
      'source_column' => [
        '#type' => 'islandora_spreadsheet_ingest_migration_mapping_source',
        '#properties' => static::getSourceProperties($element, $form_state),
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
        '#limit_validation_errors' => [
          array_merge($element['#parents'], ['add_mapping']),
          array_merge($element['#parents'], ['add_mapping', 'source_column']),
          array_merge($element['#parents'], ['add_mapping', 'destination']),
        ],
        '#validate' => [
          [static::class, 'validateAddMapping'],
        ],
        '#submit' => [
          [static::class, 'submitAddMapping'],
        ],
      ],
    ];
    return $element;
  }

  protected static function getMigrationStorage(MigrationInterface $migration, FormStateInterface $form_state) {
    return $form_state->getStorage()[$migration->id()] ?: [
      'autopopulated' => FALSE,
      'entries' => [],
    ];
  }
  protected static function getEntries(MigrationInterface $migration, FormStateInterface $form_state) {
    return $form_state->getStorage()[$migration->id()]['entries'];
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
        return isset($entries[$source]) ? $entries[$source] : new RowSource($source, t('Unknown'));
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
    $autopop_target = [
      $element['#migration']->id(),
      'autopopulated',
    ];
    if (!NestedArray::getValue($form_state->getStorage(), $autopop_target)) {
      // Load up the entries from the migration.
      $entries = static::mapMigrationProcessToPipelines($element['#migration']);
      static::setEntries($element['#migration'], $form_state, $entries);

      $unused_props_in_source = array_intersect_key(
        static::getSourceProperties($element, $form_state),
        static::getUnusedDestinationProperties($element['#migration'], $form_state)
      );

      foreach ($unused_props_in_source as $name => $unused) {
        $entries[$name] = new Pipeline(
          $unused,
          $name
        );
      }
      static::setEntries($element['#migration'], $form_state, $entries);
      NestedArray::setValue($form_state->getStorage(), $autopop_target, TRUE);
    }

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

  public static function validateAddMapping(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $element_target = array_merge(
      array_slice($trigger['#array_parents'], 0, -2),
      ['#migration']
    );
    $migration = NestedArray::getValue($form, $element_target);

    $adder = array_slice($trigger['#array_parents'], 0, -1);
    $source_target = array_merge($adder, ['source_column']);
    $source_el = NestedArray::getValue($form, $source_target);
    $source = $form_state->getValue($source_target);
    $destination = $form_state->getValue(array_merge($adder, ['destination']));

    if ($source instanceof SourceInterface) {
      $form_state->setTemporaryValue('new', new Pipeline(
        $source,
        $destination
      ));
    }
  }

  public static function submitAddMapping(array &$form, FormStateInterface $form_state) {
    // Add the entry to form state and rebuild.
    $trigger = $form_state->getTriggeringElement();
    $element_target = array_slice($trigger['#array_parents'], 0, -2);
    $migration_target = array_merge(
      $element_target,
      ['#migration']
    );
    $migration = NestedArray::getValue($form, $migration_target);

    $new = $form_state->getTemporaryValue('new');
    static::setEntries($migration, $form_state, array_merge(
      static::getEntries($migration, $form_state),
      [
        $new->getName() => $new,
      ]
    ));


    dsm($form_state, 'fs');
    NestedArray::unsetValue($form, ['mapping']);
    NestedArray::unsetValue($form_state->getCompleteForm(), ['mapping']);
    $form_state->setProcessInput(FALSE);
    $form_state->setRebuild();
  }

  public static function validateRemoveMapping(array $form, FormStateInterface $form_state) {
    // Remove the selected entries from the form state and rebuild.
    $trigger = $form_state->getTriggeringElement();
    $element_target = array_slice($trigger['#array_parents'], 0, -1);
    $target = array_merge($element_target, ['table']);
    $table_element = NestedArray::getValue($form, $target);

    $table = $form_state->getValue($target);

    $selected = array_filter($table, function ($row) {
      return $row['select'];
    });

    if ($selected) {
      $form_state->setTemporaryValue('selected', $selected);
    }
    else {
      $form_state->setError($table_element, t('Nothing selected!'));
    }

  }
  public static function submitRemoveMapping(array $form, FormStateInterface $form_state) {
    // Remove the selected entries from the form state and rebuild.
    $trigger = $form_state->getTriggeringElement();
    $element_target = array_merge(
      array_slice($trigger['#array_parents'], 0, -1),
      ['#migration']
    );
    $migration = NestedArray::getValue($form, $element_target);

    static::setEntries($migration, $form_state, array_diff_key(
      static::getEntries($migration, $form_state),
      $form_state->getTemporaryValue('selected')
    ));

    $form_state->setRebuild();
  }

}
