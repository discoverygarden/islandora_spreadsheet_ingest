<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Splits a string into an array of associative arrays, using two delimiters.
 *
 * Intended to be used with multi-valued fields with multiple properties. Since
 * we may be placing multiple values into multiple field properties, we need a
 * way to build a multi-valued array out of a single cell, including keys. It
 * is possible to do this with a complicated pipeline structure using existing
 * process plugins; the intent here is to provide a much more user friendly way
 * of tying cell contents to complex multi-valued fields, both on the side of
 * the CSV and the side of the yaml configuration.
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
 *   not used, the resultant subset arrays will be indexed from 0.
 *
 * For example, given the following cell contents:
 *
 * @code
 * first_property|second_property|third_property; first_new_property|second_new_property
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
class SubDelimitedExplode extends ProcessPluginBase {

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

    if ($value === '') {
      return [];
    }

    // Establish some variables.
    $limit = isset($this->configuration['limit']) ? $this->configuration['limit'] : PHP_INT_MAX;
    $sublimit = isset($this->configuration['sublimit']) ? $this->configuration['sublimit'] : PHP_INT_MAX;
    $keys = isset($this->configuration['keys']) ? $this->configuration['keys'] : [];

    // Build and return the array. Resultant array should use keys from config;
    // if those run out, use the native index of each item.
    $out = [];
    foreach (explode($this->configuration['delimiter'], $value, $limit) as $top_level) {
      $sub_pieces = explode($this->configuration['subdelimiter'], $top_level, $sublimit);
      foreach ($sub_pieces as $idx => $piece) {
        $key = isset($keys[$idx]) ? $keys[$idx] : $idx;
        $sub_pieces[$key] = $piece;
        if ($key !== $idx) {
          unset($sub_pieces[$idx]);
        }
      }
      $out[] = $sub_pieces;
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
