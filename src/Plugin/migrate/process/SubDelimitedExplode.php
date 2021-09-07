<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

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
 * - trim: (optional) Flag indicating if whitespace should be trimmed from top-
 *   level elements. Defaults to TRUE.
 * - subtrim: (optional) Flag indicating if whitespace should be trimmed from
 *   subelements. Defaults to TRUE.
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
class SubDelimitedExplode extends ProcessPluginBase {

  /**
   * Corresponds to the 'limit' variable for explode.
   *
   * See the PHP user's manual for explode() for more details; the short
   * version is that positive numbers limit the number of subdivisions you get
   * (the last element being the rest of the string), and negative numbers
   * remove that number of subdivisions from the end of the resultant list.
   *
   * @var int
   */
  protected $limit;

  /**
   * The same as 'limit', but for individual items inside each element.
   *
   * Each element can have items up to the one given sublimit.
   *
   * @var int
   */
  protected $sublimit;

  /**
   * An array of the names of keys to apply to subsets, in order.
   *
   * Use this to tie the structure of the subset to the keys needed by the
   * destination field. If not used, the resultant subset arrays will be indexed
   * from 0. If a subset array has more values than keys given, additional keys
   * will be provided at the same native index in the subset.
   *
   * @var array
   */
  protected $keys;

  /**
   * The boundary string for top-level elements.
   *
   * @var string
   */
  protected $delimiter;

  /**
   * The boundary string for individual items inside each element.
   *
   * @var string
   */
  protected $sublimiter;

  /**
   * Indicate if each top-level element should have its space trimmed.
   *
   * @var bool
   */
  protected $trim;

  /**
   * Indicate if each subelement should have its space trimmed.
   *
   * @var bool
   */
  protected $subtrim;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Run some tests on the data.
    if (empty($this->configuration['delimiter']) || empty($this->configuration['subdelimiter'])) {
      throw new MigrateException("The 'delimiter' and 'subdelimiter' properties must both be provided for subdelimited_explode processes.");
    }
    if (!empty($this->configuration['keys']) && !is_array($this->configuration['keys'])) {
      throw new MigrateException("The list of 'keys' provided should be an array.");
    }

    // Establish some variables.
    $this->limit = $this->configuration['limit'] ?? PHP_INT_MAX;
    $this->sublimit = $this->configuration['sublimit'] ?? PHP_INT_MAX;
    $this->keys = $this->configuration['keys'] ?? [];
    $this->delimiter = $this->configuration['delimiter'];
    $this->subdelimiter = $this->configuration['subdelimiter'];
    $this->trim = $this->configuration['trim'] ?? TRUE;
    $this->subtrim = $this->configuration['subtrim'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // Run some tests on the data.
    if (!is_string($value)) {
      throw new MigrateException(sprintf('The value provided to subdelimited_explode must be a string; actual provided value: %s', var_export($value, TRUE)));
    }

    if ($value === '') {
      return [];
    }

    // Build and return the array. Resultant array should use keys from config;
    // if those run out, use the native index of each item.
    $out = explode($this->delimiter, $value, $this->limit);

    // XXX: No need to trim at the top-level if subtrimming, as the subtrimming
    // will account for it.
    if (!$this->subtrim && $this->trim) {
      $out = array_map('trim', $out);
    }

    array_walk($out, [$this, 'walker']);

    return $out;
  }

  /**
   * Explode and key values according to configuration.
   *
   * @param mixed $top_level
   *   A reference to a string to explode (turn into an array) and key.
   */
  protected function walker(&$top_level) {
    $top_level = explode($this->subdelimiter, $top_level, $this->sublimit);

    if ($this->subtrim) {
      $top_level = array_map('trim', $top_level);
    }

    foreach ($top_level as $idx => $piece) {
      $key = $this->keys[$idx] ?? $idx;
      $top_level[$key] = $piece;
      if ($key !== $idx) {
        unset($top_level[$idx]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
