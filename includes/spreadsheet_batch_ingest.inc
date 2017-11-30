<?php

/**
 * @file
 * Spreadsheet batch ingest preprocessor.
 */

/**
 * For exceptional cases during preprocessing/processing.
 */
class IslandoraSpreadsheetIngestBatchException extends Exception {
}

/**
 * Class definition for spreadsheet ingest.
 */
class IslandoraSpreadsheetIngestBatch extends IslandoraBatchPreprocessor {

  /**
   * Total number of items processed.
   *
   * @var int
   */
  protected $total = 0;

  /**
   * Preprocess the batch.
   */
  public function preprocess() {
    // Open the CSV file and start reading lines.
    $csv_file = fopen($this->parameters['spreadsheet_file'], 'r');
    $header_line = fgetcsv($csv_file, 0, $this->parameters['cell_delimiter']);
    // Key map will be used later to determine values for columns.
    $key_map = array_flip($header_line);
    // If parent objects are being specified, there is the potential that the
    // parent object may reference a row instead of a PID. We'll hang onto those
    // rows for later once the parents are assigned PIDs.
    $parent_header = isset($key_map['parent_object']);
    // Store rows that have row ID as parents here.
    $rid_parent_rows = array();
    // Store a map of row ID to PID here.
    $pid_row_map = array();
    $base_parameters = $this->getBaseParameters($key_map);
    $current_row = 0;
    $added = 0;
    while (!feof($csv_file)) {
      $row = fgetcsv($csv_file, 0, $this->parameters['cell_delimiter']);
      $current_row++;
      // Blank lines are considered NULL.
      if (!is_null($row)) {
        // If the 'parent_object' is an integer, it's also a row and should be
        // left until the non-integer rows are processed.
        if ($parent_header && intval($row[$key_map['parent_object']]) > 0) {
          $rid_parent_rows[$current_row] = $row;
          continue;
        }
        $parameters = $base_parameters + array(
          'value_map' => $row,
        );
        if ($parent_header) {
          $parameters['parent_pid'] = $row[$key_map['parent_object']];
        }
        $object = new IslandoraSpreadsheetIngestBatchObject($this->connection, $parameters);
        $this->addToDatabase($object);
        $added++;
        $pid_row_map[$current_row] = $object->id;
      }
    }
    // Run through the rows that had row IDs as parents repeatedly until none
    // are left.
    while (!empty($rid_parent_rows)) {
      $remaining = array();
      foreach ($rid_parent_rows as $rid => $row) {
        if (!isset($pid_row_map[$row[$key_map['parent_object']]])) {
          $remaining[$rid] = $row;
          continue;
        }
        $parameters = $base_parameters + array(
          'value_map' => $row,
          'parent_pid' => $pid_row_map[$row[$key_map['parent_object']]],
        );
        $object = new IslandoraSpreadsheetIngestBatchObject($this->connection, $parameters);
        $this->addToDatabase($object);
        $added++;
      }
      // If the list hasn't shrunk any, we either have a cyclical dependency
      // that can't be resolved, or some rows reference out-of-range parents.
      if (count($remaining) == count($rid_parent_rows)) {
        throw new IslandoraSpreadsheetIngestBatchException(t('Failed to preprocess the batch: either rows specify parent rows which are out of range of the CSV, or a cyclical set of relationships has been established in the CSV that cannot be resolved.'));
        ));
      }
      $rid_parent_rows = $remaining;
    }
    $this->total = $added;
  }

  /**
   * Returns the total number of items processed.
   */
  public function getTotal() {
    return $this->total;
  }

  /**
   * Gets the base parameter set.
   *
   * @param array $key_map
   *   The key_map to add to the base parameter set.
   *
   * @return array
   *   Base parameter set.
   */
  protected function getBaseParameters(array $key_map) {
    return array(
      'template' => $this->parameters['template'],
      'binary_path' => $this->parameters['base_binary_path'],
      'key_map' => $key_map,
    );
  }

}

/**
 * Class definition for spreadsheet ingest objects.
 */
class IslandoraSpreadsheetIngestBatchObject extends IslandoraBatchObject {

  protected $connection;
  protected $parameters;

  /**
   * Parameters to be imploded and passed on to Saxon.
   *
   * @var array
   */
  protected $xsltParameters = array();

  /**
   * Class constructor.
   *
   * @param IslandoraTuque $connection
   *   Connection to Fedora.
   * @param array $parameters
   *   Associative array containing preprocessor parameters, requiring:
   *   - 'namespace': A namespace to give this object.
   *   - 'template': The ID of the template to use with the given value map.
   *   - 'binary_path': Base path to a binary for this object, or NULL if not
   *     specified.
   *   - 'key_map': Associative array mapping column headers to their column
   *     position.
   *   - 'value_map': Array of string values.
   *   - 'parent_pid': The parent PID of this item, or NULL if not specified.
   *   - 'cmodel': The content model of this item.
   */
  public function __construct(IslandoraTuque $connection, array $parameters) {
    $namespace = empty($parameters['namespace']) ? NULL : $parameters['namespace'];
    parent::__construct($namespace, $connection->repository);
    $this->connection = $connection;
    $this->parameters = $parameters;
  }

  /**
   * Gets a value from the value_map.
   *
   * @param string $key
   *   The key to get a value for.
   *
   * @return string|bool|null
   *   Returns the value of the key given in the value_map. Returns FALSE if
   *   there is no value for the given key. Returns NULL if the given key does
   *   not exist.
   */
  protected function getValue($key) {
    if (!isset($this->parameters['key_map'][$key])) {
      return NULL;
    }
    if (empty($this->parameters['value_map'][$this->parameters['key_map'][$key]])) {
      return FALSE;
    }
    return $this->parameters['value_map'][$this->parameters['key_map'][$key]];
  }

  /**
   * Get resources for this item.
   */
  public function getResources() {
    return array();
  }

  /**
   * Processes the object.
   */
  public function batchProcess() {
    try {
      $this->addMetadata();
      $this->addBinary();
      $this->addRelationships();
      $label = $this->getValue('label');
      if ($label) {
        $this->label = $label;
      }
    }
    catch (Exception $e) {
      return array(
        'state' => ISLANDORA_BATCH_STATE__ERROR,
        'message' => t('Failed to process the object: @e', array(
          '@e' => $e->getMessage(),
        )),
      );
    }
    return ISLANDORA_BATCH_STATE__DONE;
  }

  /**
   * Adds metadata by transforming the value_map through the template.
   */
  public function addMetadata() {
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
    $template = islandora_spreadsheet_ingest_get_template($this->parameters['template']);
    if (!$template) {
      throw new IslandoraSpreadsheetIngestBatchException(t('Template referenced with ID @id does not exist', array(
        '@id' => $this->parameters['template'],
      )));
    }
    $template_file = file_load($template['fid']);
    $ds_exists = isset($this[$template['dsid']]);
    if (!$ds_exists) {
      $ds = $this->constructDatastream($template['dsid'], 'M');
      $ds->label = t('@dsid Datastream', array('@dsid' => $template['dsid']));
    }
    else {
      $ds = $this[$template['dsid']];
    }
    $ds->setContentFromString($this->transformWithSaxon($template_file->uri));
    if (!$ds_exists) {
      $this->ingestDatastream($ds);
    }
  }

  /**
   * Transforms the value_map into XML using the template.
   *
   * @param string $template_uri
   *   The URI of the template to use with Saxon.
   *
   * @return string
   *   Transformed XML.
   */
  protected function transformWithSaxon($template_uri) {
    $this->buildParameterList();
    $pipes = array();
    $param_string = implode(' ', $this->xsltParameters);
    $command = variable_get('islandora_spreadsheet_ingest_saxon_command', '/usr/bin/saxonb-xslt');
    $xsl_path = drupal_realpath($template_uri);
    $process = proc_open("$command -versionmsg:off -ext:on -it:root $xsl_path $param_string",
      array(
        // STDIN; not used.
        0 => array(
          'pipe',
          'r',
        ),
        // STDOUT; XML comes out the end here.
        1 => array(
          'pipe',
          'w',
        ),
        // STDERR; used to determine if Saxon-B failed.
        2 => array(
          'pipe',
          'w',
        ),
      ),
      $pipes);
    $transformed = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    // If STDERR was written to, something went wrong.
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($process);
    if (!empty($stderr)) {
      throw new IslandoraSpreadsheetIngestBatchException(filter_xss($stderr));
    }
    return $transformed;
  }

  /**
   * Builds a list of parameters to be passed to Saxon.
   */
  protected function buildParameterList() {
    $parameters = array();
    foreach ($this->parameters['key_map'] as $key => $idx) {
      if (!empty($this->parameters['value_map'][$idx])) {
        $parameters[] = escapeshellarg("{$key}={$this->parameters['value_map'][$idx]}");
      }
    }
    $this->xsltParameters = $parameters;
  }

  /**
   * Adds the OBJ datastream if necessary.
   */
  public function addBinary() {
    $binary_file = $this->getValue('binary_file');
    if ($binary_file) {
      // Normalize the path on the way through.
      $path = rtrim($this->parameters['binary_path'], '/') . '/' . ltrim($binary_file, '/');
      if (!is_readable($path)) {
        throw new IslandoraSpreadsheetIngestBatchException(t('Failed to find or read the given binary file at @path', array(
          '@path' => $path,
        )));
      }
      $exists = isset($this['OBJ']);
      if (!$exists) {
        $obj = $this->constructDatastream('OBJ', 'M');
        $obj->label = t('OBJ Datastream');
      }
      else {
        $obj = $this['OBJ'];
      }
      $obj->setContentFromFile($path);
      if (!$exists) {
        $this->ingestDatastream($obj);
      }
    }
  }

  /**
   * Adds the required relationships to this object.
   */
  public function addRelationships() {
    $this->models = $this->getValue('cmodel');
    if (!empty($this->parameters['parent_pid'])) {
      $parent_uri = $this->getValue('parent_uri');
      $parent_predicate = $this->getValue('parent_predicate');
      $this->relationships->add($parent_uri ? $parent_uri : FEDORA_RELS_EXT_URI,
        $parent_predicate ? $parent_predicate : 'isMemberOfCollection',
        $this->parameters['parent_pid']);
    }
  }

}