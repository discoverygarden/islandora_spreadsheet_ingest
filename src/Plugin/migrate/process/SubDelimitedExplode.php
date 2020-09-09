<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Splits a string into an array of associative arrays, using two delimiters.
 *
 * Intended to be used with multi-valued fields with multiple properties. We
 * need a way to represent these kinds of structures in a single cell, including
 * keys. While it is technically possible to do this with a complicated pipeline
 * structure using existing process plugins, the intent here is to provide a
 * much more user-friendly way of tying cell contents to complex multi-valued
 * fields, both on the side of the CSV and the side of the yaml configuration,
 * that expands on how CSV already works.
 *
 * Available configuration keys:
 * - source: The source string.
 * - delimiter: The boundary string for top-level elements.
 * - limit: (optional) Corresponds to the 'limit' variable for explode; see the
 *   PHP user's manual for explode() for more details; the short version is that
 *   positive numbers limit the number of subdivisions you get (the last element
 *   being the rest of the string), and negative numbers remove that number of
 *   subdivisions from the end of the resultant list.
 * - subdelimiter: The boundary string for individual items inside each element.
 * - sublimit: (optional) The same as 'limit', but for individual items inside
 *   each element. Each element can have items up to the one given sublimit.
 * - keys: (optional) An array of the names of keys to apply to subsets, in the
 *   order they should be applied to each item in each subset. Use this to tie
 *   the structure of the subset to the keys needed by the destination field. If
 *   not used, the resultant subset arrays will be indexed from 0. If a subset
 *   array has more values than keys given, additional keys will be provided at
 *   the same native index in the subset.
 *
 * For example, given the following cell contents:
 *
 * @code
 * first_property|second_property|third_property; first_new_property|second_new_property; another_property|another_other_property|more_properties|extra_property
 * @endcode
 *
 * Placed through the following .yaml:
 *
 * @code
 * my_cool_property:
 *   - plugin: subdelimited_explode
 *     delimiter: '; '
 *     subdelimiter: '|'
 *     source: cell
 *     keys:
 *       - first_key
 *       - second_key
 *       - third_key
 * some_complex_field: '@_my_cool_property'
 * @endcode
 *
 * Would result in the following array:
 *
 * @code
 * source: Array
 * (
 *   [cell] => Array
 *     (
 *       [0] => Array
 *         (
 *           [first_key] => "first_property"
 *           [second_key] => "second_property"
 *           [third_key] => "third_property"
 *         )
 *       [1] => Array
 *         (
 *           [first_key] => "first_new_property"
 *           [second_key] => "second_new_property"
 *         )
 *       [2] => Array
 *         (
 *           [first_key] => "another_property"
 *           [second_key] => "another_other_property"
 *           [third_key] => "more_properties"
 *           [3] => "extra_property"
 *         )
 *     )
 * )
 * @endcode
 *
 * With each nested array being used as a new 'some_complex_field' entry.
 *
 * @MigrateProcessPlugin(
 *   id = "subdelimited_explode"
 * )
 */
class SubDelimitedExplode extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    $instance = new static(
      $configuration,
      $pluginId,
      $pluginDefinition
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Run some tests on the data.
    if (empty($this->configuration['delimiter']) || empty($this->configuration['subdelimiter'])) {
      throw new MigrateException("The 'delimiter' and 'subdelimiter' properties must both be provided for subdelimited_explode processes.");
    }
    if (!is_string($value)) {
      throw new MigrateException(sprintf('The value provided to subdelimited_explosion must be a string; actual provided value: %s', var_export($value, TRUE)));
    }
    if (!empty($this->configuration['keys']) && !is_array($this->configuration['keys'])) {
      throw new MigrateException("The list of 'keys' provided should be an array.");
    }
    if (!empty($this->configuration['keys_and_transform_info']) && !is_array($this->configuration['keys_and_transform_info'])) {
      throw new MigrateException("The list for 'keys_and_transform_info' provided should be an array.");
    }

    if ($value === '') {
      return [];
    }

    // Establish some variables.
    $limit = isset($this->configuration['limit']) ? $this->configuration['limit'] : PHP_INT_MAX;
    $sublimit = isset($this->configuration['sublimit']) ? $this->configuration['sublimit'] : PHP_INT_MAX;
    $keys = isset($this->configuration['keys']) ? $this->configuration['keys'] : [];
    $subdelimiter = $this->configuration['subdelimiter'];
    $keys_and_transform_info = isset($this->configuration['keys_and_transform_info']) ? $this->configuration['keys_and_transform_info'] : [];

    // Build and return the array. Resultant array should use keys from config;
    // if those run out, use the native index of each item.
    $out = explode($this->configuration['delimiter'], $value, $limit);
    array_walk($out, function (&$top_level) use ($sublimit, $subdelimiter, $keys) {
      $top_level = explode($subdelimiter, $top_level, $sublimit);
      foreach ($top_level as $idx => $piece) {
        $key = isset($keys[$idx]) ? $keys[$idx] : $idx;
        $top_level[$key] = $piece;
        if ($key !== $idx) {
          unset($top_level[$idx]);
        }
      }
    });

    if (!empty($keys_and_transform_info)) {
      foreach ($keys_and_transform_info as $key => $query_info) {
        foreach ($out as &$field_array) {
          if (array_key_exists($key, $field_array)) {
            $results = $this->entityTypeManager->getStorage($query_info['entity_type'])
              ->getQuery()
              ->accessCheck($query_info['accessCheck'])
              ->condition($query_info['value_key'], $field_array[$key])
              ->condition($query_info['bundle_key'], $query_info['bundle'])
              ->range(0, 1)
              ->latestRevision()
              ->execute();
            $result = reset($results);
            $field_array[$key] = $result;
          }
        }
      }
    }
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
