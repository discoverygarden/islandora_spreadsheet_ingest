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
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
    // Open the CSV file and start reading lines.
    $csv_file = fopen($this->parameters['spreadsheet_file'], 'r');
    if (!$csv_file) {
      throw new InvalidArgumentException(t('Failed to open @file.', array('@file' => $this->parameters['spreadsheet_file'])));
    }
    $header_line = fgetcsv($csv_file, 0, $this->parameters['cell_delimiter']);
    // Key map will be used later to determine values for columns.
    $key_map = array_flip($header_line);

    // For backwards compatibility, check for a binary_file column.
    if (in_array('binary_file', $key_map) && !isset($key_map['object_location'])) {
      $key_map['object_location'] = $key_map['binary_file'];
    }

    $base_parameters = $this->getBaseParameters($key_map);
    $row_count = 0;

    while ($csv_file && !feof($csv_file)) {
      $row_count++;
      $row = fgetcsv($csv_file, 0, $this->parameters['cell_delimiter']);
      if ($row === array(NULL)) {
        // XXX: As per the documentation: "A blank line in a CSV file will be
        // returned as an array comprising a single null field, and will not be
        // treated as an error."... We want to skip it.
        //
        // @see http://php.net/manual/function.fgetcsv.php
        continue;
      }
      // If header contains object_location, validate file in that column.
      if (in_array('object_location', $header_line)) {
        $object_location = $row[$key_map['object_location']];
        if ($object_location && !valid_url($object_location, TRUE)) {
          // Normalize the path on the way through.
          $path = islandora_spreadsheet_ingest_normalize_binary_file_path($this->parameters['base_binary_path'], $object_location);
          if (!is_readable($path)) {
            throw new IslandoraSpreadsheetIngestBatchException(t('Failed to find or read the given path (@path) on line @row of @csv', array(
              '@path' => $path,
              '@row' => $row_count,
              '@csv' => $this->parameters['spreadsheet_file'],
            )));
          }
        }
      }
      $parameters = $base_parameters + array(
        'value_map' => $row,
        'dsid' => $this->parameters['dsid'],
      );
      $object = IslandoraSpreadsheetIngestBatchObject::create($this->connection, $parameters);
      try {
        // Only add to be ingested if it does not already exist.
        if (!islandora_object_load($object->id)) {
          call_user_func_array(array(
            $this,
            'addToDatabase',
          ), $object->getInitialDatabaseInfo($this->getSetId()));
          $this->total++;
        }
      }
      catch (Exception $e) {
        watchdog_exception('islandora_spreadsheet_ingest', $e);
        if ($e instanceof PDOException) {
          drupal_set_message(t('Items in the batch set are incomplete due to duplicate PIDs (@identifier). Please review existing sets.', array(
            '@identifier' => $object->id,
          )), 'error');
        }
        else {
          drupal_set_message(t('An exception occurred during preprocessing, check the watchdogs for more information.'), 'error');
        }
      }
    }
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
    // XXX: The aggregate OCR option is dependent on generating OCR being
    // selected.
    $aggregate_ocr = FALSE;
    if (isset($this->parameters['paged_content_generate_ocr']) && (bool) $this->parameters['paged_content_generate_ocr'] && isset($this->parameters['paged_content_aggregate_ocr'])) {
      $aggregate_ocr = (bool) $this->parameters['paged_content_aggregate_ocr'];
    }
    return array(
      'template' => $this->parameters['template'],
      'binary_path' => $this->parameters['base_binary_path'],
      'key_map' => $key_map,
      'create_pdfs' => isset($this->parameters['paged_content_create_pdfs']) ? (bool) $this->parameters['paged_content_create_pdfs'] : FALSE,
      'generate_ocr' => isset($this->parameters['paged_content_generate_ocr']) ? (bool) $this->parameters['paged_content_generate_ocr'] : FALSE,
      'generate_hocr' => isset($this->parameters['paged_content_generate_hocr']) ? (bool) $this->parameters['paged_content_generate_hocr'] : FALSE,
      'aggregate_ocr' => $aggregate_ocr,
      'self_transform' => $this->parameters['self_transform'],
      'transform_dsid' => $this->parameters['transform'] ? $this->parameters['transform_dsid'] : FALSE,
      'transform_template' => $this->parameters['transform'] ? $this->parameters['transform_template'] : FALSE,
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

  // The 'type' of alteration this object performs.
  const ALTER_TYPE = 'islandora_spreadsheet_ingest_object';

  /**
   * Class constructor.
   *
   * @param IslandoraTuque $connection
   *   Connection to Fedora.
   * @param array $parameters
   *   Associative array containing preprocessor parameters, requiring:
   *   - 'namespace': A namespace to give this object if a 'pid' isn't set in
   *     the value_map.
   *   - 'template': The ID of the template to use with the given value map.
   *   - 'binary_path': Base path to a binary for this object, or NULL if not
   *     specified.
   *   - 'key_map': Associative array mapping column headers to their column
   *     position.
   *   - 'value_map': Array of string values.
   *   - 'dsid': The ID of the datastream that the template will generate.
   *   - 'create_pdfs': Whether to create PDFs for paged content parent objects.
   *   - 'generate_ocr': Whether to generate OCR for paged content children.
   *   - 'generate_hocr': Whether to generate HOCR for paged content children.
   *   - 'aggregate_ocr': Whether to aggregate OCR from children to parents.
   *   - 'self_transform': A self transformation to be ran or 'No Transform'.
   *   - 'transform_dsid': The DSID to be transformed to or FALSE if one is not
   *   occurring.
   *   - 'transform_template': The XSL to be used for transforming or FALSE if
   *   one is not occurring.
   */
  public function __construct(IslandoraTuque $connection, array $parameters) {
    $this->connection = $connection;
    $this->parameters = $parameters;
    $id = $this->getValue('pid', empty($this->parameters['namespace']) ? NULL : $this->parameters['namespace']);
    parent::__construct($id, $connection->repository);
  }

  /**
   * Creates a new typed object given the content model.
   *
   * @param IslandoraTuque $connection
   *   Connection to Fedora.
   * @param array $parameters
   *   Associative array containing preprocessor parameters, requiring:
   *   - 'namespace': A namespace to give this object if a 'pid' isn't set in
   *     the value_map.
   *   - 'template': The ID of the template to use with the given value map.
   *   - 'binary_path': Base path to a binary for this object, or NULL if not
   *     specified.
   *   - 'key_map': Associative array mapping column headers to their column
   *     position.
   *   - 'value_map': Array of string values.
   *   - 'dsid': The ID of the datastream that the template will generate.
   *   - 'create_pdfs': Whether to create PDFs for paged content parent objects.
   *   - 'generate_ocr': Whether to generate OCR for paged content children.
   *   - 'generate_hocr': Whether to generate HOCR for paged content children.
   *   - 'aggregate_ocr': Whether to aggregate OCR from children to parents.
   *   - 'self_transform': A self transformation to be ran or 'No Transform'.
   *   - 'transform_dsid': The DSID to be transformed to or FALSE if one is not
   *   occurring.
   *   - 'transform_template': The XSL to be used for transforming or FALSE if
   *   one is not occurring.
   *
   * @return IslandoraSpreadsheetIngestPagedContentParentBatchObject|IslandoraSpreadsheetIngestPagedContentChildBatchObject|IslandoraSpreadsheetIngestBatchObject
   *   The object to be ingested.
   */
  public static function create(IslandoraTuque $connection, array $parameters) {
    $parent_paged = static::getPagedContentModelInfo();
    $child_paged = static::getPagedContentModelPages();
    $cmodel = static::getValueFromArray($parameters, 'cmodel');

    if (isset($parent_paged[$cmodel])) {
      return new IslandoraSpreadsheetIngestPagedContentParentBatchObject($connection, $parameters);
    }
    elseif (isset($child_paged[$cmodel])) {
      return new IslandoraSpreadsheetIngestPagedContentChildBatchObject($connection, $parameters);
    }
    else {
      return new static($connection, $parameters);
    }
  }

  /**
   * Gets a value from the value_map.
   *
   * @param string $key
   *   The key to get a value for.
   * @param string|null $default
   *   A default value to fall back on.
   *
   * @return mixed
   *   Returns the value of the key given in the value_map. Returns the default
   *   if the key doesn't exist or the value is not set for it.
   */
  protected function getValue($key, $default = NULL) {
    return static::getValueFromArray($this->parameters, $key, $default);
  }

  /**
   * Gets a value directly from the value_map array in the parameters.
   *
   * @param array $params
   *   Associative array containing preprocessor parameters, requiring:
   *   - 'namespace': A namespace to give this object if a 'pid' isn't set in
   *     the value_map.
   *   - 'template': The ID of the template to use with the given value map.
   *   - 'binary_path': Base path to a binary for this object, or NULL if not
   *     specified.
   *   - 'key_map': Associative array mapping column headers to their column
   *     position.
   *   - 'value_map': Array of string values.
   *   - 'dsid': The ID of the datastream that the template will generate.
   *   - 'create_pdfs': Whether to create PDFs for paged content parent objects.
   *   - 'generate_ocr': Whether to generate OCR for paged content children.
   *   - 'generate_hocr': Whether to generate HOCR for paged content children.
   *   - 'aggregate_ocr': Whether to aggregate OCR from children to parents.
   *   - 'self_transform': A self transformation to be ran or 'No Transform'.
   *   - 'transform_dsid': The DSID to be transformed to or FALSE if one is not
   *   occurring.
   *   - 'transform_template': The XSL to be used for transforming or FALSE if
   *   one is not occurring.
   * @param string $key
   *   The key to get a value for.
   * @param string|null $default
   *   A default value to fall back on.
   *
   * @return mixed
   *   Returns the value of the key given in the value_map. Returns the default
   *   if the key doesn't exist or the value is not set for it.
   */
  protected static function getValueFromArray(array $params, $key, $default = NULL) {
    if (!isset($params['key_map'][$key]) || empty($params['value_map'][$params['key_map'][$key]])) {
      return $default;
    }
    return $params['value_map'][$params['key_map'][$key]];
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
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
    try {
      $this->addMetadata();
      $object_location = $this->getValue('object_location');
      if ($object_location) {
        if (valid_url($object_location, TRUE)) {
          $this->addObj($object_location);
        }
        else {
          $object_location = islandora_spreadsheet_ingest_normalize_binary_file_path($this->parameters['binary_path'], $object_location);
          if (is_file($object_location)) {
            $this->addObj($object_location);
          }
          elseif (is_dir($object_location)) {
            $this->addDatastreams($object_location);
          }
        }
      }
      $this->relationships->autoCommit = FALSE;
      $this->addRelationships();
      $label = $this->getValue('label');
      if ($label) {
        $this->label = $label;
      }
      $this->alter();
      $this->relationships->commitRelationships();
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
    $dsid = $this->parameters['dsid'];
    $template_uri = isset($template['fid']) ?
      file_load($template['fid'])->uri : $template['uri'];
    $ds_exists = isset($this[$dsid]);
    if (!$ds_exists) {
      $ds = $this->constructDatastream($dsid, 'M');
      $ds->label = t('@dsid Datastream', array('@dsid' => $dsid));
      $ds->mimetype = 'application/xml';
    }
    else {
      $ds = $this[$dsid];
    }
    $dom = new DOMDocument();
    $dom->loadXML($this->transformWithSaxon($template_uri));
    $dom = xml_form_builder_transform_metadata_datastream($dom, $this->parameters['self_transform']);
    $ds->setContentFromString($dom->saveXML());
    if (!$ds_exists) {
      $this->ingestDatastream($ds);
    }

    if ($this->parameters['transform_dsid']) {
      $transform_dsid = $this->parameters['transform_dsid'];
      $transform_ds_exists = isset($this[$transform_dsid]);
      if ($transform_ds_exists) {
        $transform_ds = $this[$transform_dsid];
      }
      else {
        $transform_ds = $this->constructDatastream($transform_dsid);
        $transform_ds->label = t('@dsid Datastream', array('@dsid' => $transform_dsid));
        $transform_ds->mimetype = 'application/xml';
      }
      $transform_dom = new DOMDocument();
      $transform_dom->loadXML($this[$dsid]->content);
      $transform_file = xml_form_builder_get_transform_file($this->parameters['transform_template']);
      $transform_dom = xml_form_builder_transform_document($transform_file, $transform_dom);
      $transform_ds->content = $transform_dom->saveXML();
      if (!$transform_ds_exists) {
        $this->ingestDatastream($transform_ds);
      }
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
    $xsl_path = escapeshellarg(drupal_realpath($template_uri));
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
    foreach ($this->parameters['key_map'] as $key => $idx) {
      if (!empty($this->parameters['value_map'][$idx])) {
        $this->xsltParameters[] = escapeshellarg("{$key}={$this->parameters['value_map'][$idx]}");
      }
    }
  }

  /**
   * Adds the OBJ datastream at the given location.
   *
   * @param string $object_location
   *   The relative path to the file to use as the OBJ, or an absolute URL to
   *   use as a redirect datastream OBJ.
   */
  public function addObj($object_location) {
    $exists = isset($this['OBJ']);
    if ($exists) {
      $obj = $this['OBJ'];
    }

    if (valid_url($object_location, TRUE)) {
      if ($exists && !in_array($obj->controlGroup, array('E', 'R'))) {
        // The controlGroup is immutable, and we have to attach a URL,
        // so purge this OBJ so we can create a fresh one.
        $this['OBJ']->purgeDatastream('OBJ');
        $exists = FALSE;
      }

      if (!$exists) {
        $obj = $this->constructDatastream('OBJ', 'R');
      }
      $obj->url = $object_location;
      $obj->label = pathinfo($object_location, PATHINFO_BASENAME);
      $obj->mimetype = file_get_mimetype($object_location);
      if (!$exists) {
        $this->ingestDatastream($obj);
      }
    }
    else {
      if (!is_readable($object_location)) {
        throw new IslandoraSpreadsheetIngestBatchException(t('Failed to find or read the given binary file at @path', array(
          '@path' => $object_location,
        )));
      }
      if (!$exists) {
        $obj = $this->constructDatastream('OBJ', 'M');
        $obj->label = t('OBJ Datastream');
      }
      else {
        $obj = $this['OBJ'];
      }
      $obj->setContentFromFile($object_location);
      $obj->mimetype = file_get_mimetype($object_location);
      if (!$exists) {
        $this->ingestDatastream($obj);
      }
    }
  }

  /**
   * Adds arbitrary datastreams in the given directory.
   *
   * @param string $object_location
   *   The relative path to a folder where arbitrary datastreams are being held.
   */
  public function addDatastreams($object_location) {
    module_load_include('inc', 'islandora', 'includes/utilities');
    if (!is_readable($object_location) || !is_dir($object_location)) {
      throw new IslandoraSpreadsheetIngestBatchException(t('Failed to find or read the path to the datastreams at @path', array(
        '@path' => $object_location,
      )));
    }
    $files = file_scan_directory(
      drupal_realpath($object_location),
      '/.*/',
      array('recurse' => FALSE));
    foreach ($files as $file) {
      if (!isset($this[$file->name])) {
        $ds = $this->constructDatastream($file->name, 'M');
      }
      else {
        $ds = $this[$file->name];
      }
      $ds->setContentFromFile($file->uri);
      $ds->mimetype = file_get_mimetype($file->uri);
      $ds->label = "{$file->name} Datastream";
      if (!isset($this[$file->name])) {
        $this->ingestDatastream($ds);
      }
    }
  }

  /**
   * Adds the required relationships to this object.
   */
  public function addRelationships() {
    $this->models = $this->getValue('cmodel');
    if ($parent_object = $this->getValue('parent_object')) {
      $parent_uri = $this->getValue('parent_uri', FEDORA_RELS_EXT_URI);
      $parent_predicate = $this->getValue('parent_predicate', 'isMemberOfCollection');
      $this->relationships->add($parent_uri, $parent_predicate, $parent_object);
    }
  }

  /**
   * Allows other modules to hook in and alter the processed object.
   */
  public function alter() {
    drupal_alter(static::ALTER_TYPE, $this);
  }

  /**
   * Helper; gather together info to write to the DB during preprocessing.
   *
   * @param int $set_id
   *   The ID of the set to which we are to be added.
   *
   * @return array
   *   An array representing the parameters which might be passed to
   *   IslandoraBatchPreprocessor::addToDatabase().
   */
  public function getInitialDatabaseInfo($set_id) {
    return array($this);
  }

  /**
   * Gets the paged content model info from the module.
   *
   * @return array
   *   An empty array or an array keyed by parent content model,
   */
  protected static function getPagedContentModelInfo() {
    static $info = array();
    if (module_exists('islandora_paged_content') && empty($info)) {
      module_load_include('inc', 'islandora_paged_content', 'includes/utilities');
      $info = islandora_paged_content_retrieve_applicable_cmodels();
    }
    return $info;
  }

  /**
   * Helper to retrieve the paged content content models.
   *
   * @return array|null
   *   An array keyed by the children content model where its values are
   *   applicable parent content models.
   */
  protected static function getPagedContentModelPages() {
    static $info = NULL;
    if ($info === NULL) {
      $info = array();
      foreach (static::getPagedContentModelInfo() as $content_model => $page_info) {
        foreach (array_keys($page_info['children']) as $child_model) {
          if (!isset($info[$child_model])) {
            $info[$child_model] = array();
          }
          $info[$child_model][] = $content_model;
        }
      }
    }
    return $info;
  }

}

/**
 * Class definition for parent paged content spreadsheet ingest objects.
 */
class IslandoraSpreadsheetIngestPagedContentParentBatchObject extends IslandoraSpreadsheetIngestBatchObject {

  /**
   * Adds relationships.
   */
  public function addRelationships() {
    module_load_include('inc', 'islandora_paged_content', 'includes/derivatives');
    parent::addRelationships();
    // Set the paged content specific relationships.
    if (isset($this->parameters['create_pdfs']) && $this->parameters['create_pdfs']) {
      islandora_paged_content_set_pdf_flag($this);
    }
    if (isset($this->parameters['aggregate_ocr']) && $this->parameters['aggregate_ocr']) {
      islandora_paged_content_set_ocr_flag($this);
    }
  }

}


/**
 * Class definition for child paged content spreadsheet ingest objects.
 */
class IslandoraSpreadsheetIngestPagedContentChildBatchObject extends IslandoraSpreadsheetIngestBatchObject {
  protected $sequence;
  protected $parent;

  /**
   * Constructor for the paged content batch object.
   *
   * @param IslandoraTuque $connection
   *   Connection to Fedora.
   * @param array $parameters
   *   Associative array containing preprocessor parameters, requiring:
   *   - 'namespace': A namespace to give this object if a 'pid' isn't set in
   *     the value_map.
   *   - 'template': The ID of the template to use with the given value map.
   *   - 'binary_path': Base path to a binary for this object, or NULL if not
   *     specified.
   *   - 'key_map': Associative array mapping column headers to their column
   *     position.
   *   - 'value_map': Array of string values.
   *   - 'dsid': The ID of the datastream that the template will generate.
   *   - 'create_pdfs': Whether to create PDFs for paged content parent objects.
   *   - 'generate_ocr': Whether to generate OCR for paged content children.
   *   - 'generate_hocr': Whether to generate HOCR for paged content children.
   *   - 'aggregate_ocr': Whether to aggregate OCR from children to parents.
   */
  public function __construct(IslandoraTuque $connection, array $parameters) {
    parent::__construct($connection, $parameters);
    $this->parent = $this->getValue('parent_object');
  }

  /**
   * Helper; gather together info to write to the DB during preprocessing.
   *
   * @param int $set_id
   *   The ID of the set to which we are to be added.
   *
   * @return array
   *   An array representing the parameters which might be passed to
   *   IslandoraBatchPreprocessor::addToDatabase().
   */
  public function getInitialDatabaseInfo($set_id) {
    $this->getSequenceNumber($set_id);
    return array($this, array(), $this->parent);
  }

  /**
   * Helper that determines the sequence of the child object.
   *
   * @param string $set_id
   *   The set ID of which the child belongs to you.
   *
   * @return int
   *   The sequence number of the child.
   */
  protected function getSequenceNumber($set_id) {
    if (!isset($this->sequence)) {
      $this->sequence = db_select('islandora_batch_queue', 'i')
        ->fields('i', array('id'))
        ->condition('sid', $set_id)
        ->condition('parent', $this->parent)
        ->execute()
        ->rowCount() + 1;
    }
    return $this->sequence;
  }

  /**
   * Adds relationships.
   */
  public function addRelationships() {
    module_load_include('inc', 'islandora_paged_content', 'includes/utilities');
    // Ignore the parent relationship that adds the membership as we are doing
    // the paged things ourselves.
    $this->models = $this->getValue('cmodel');

    // Add specific paged content relationships.
    $rels_ext = $this->relationships;
    islandora_paged_content_set_relationship($rels_ext, ISLANDORA_RELS_EXT_URI, 'isPageOf', $this->getValue('parent_object'));
    islandora_paged_content_set_relationship($rels_ext, ISLANDORA_RELS_EXT_URI, 'isSequenceNumber', (string) $this->sequence, TRUE);
    islandora_paged_content_set_relationship($rels_ext, ISLANDORA_RELS_EXT_URI, 'isPageNumber', (string) $this->sequence, TRUE);
    islandora_paged_content_set_relationship($rels_ext, ISLANDORA_RELS_EXT_URI, 'isSection', '1', TRUE);
    islandora_paged_content_set_relationship($rels_ext, FEDORA_RELS_EXT_URI, 'isMemberOf', $this->getValue('parent_object'));
    if (isset($this->parameters['generate_ocr'])) {
      module_load_include('inc', 'islandora_ocr', 'includes/derivatives');
      islandora_ocr_set_generating_rels_ext_statements($this,
        isset($this->parameters['generate_ocr']) ? $this->parameters['generate_ocr'] : FALSE,
        isset($this->parameters['generate_hocr']) ? $this->parameters['generate_hocr'] : FALSE);
    }
  }

}
