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
    // var_dump($this->parameters);die('death');
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
        'namespace' => $this->parameters['namespace'],
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

          if ($row[$key_map['cmodel']] == 'islandora:rootSerialCModel') {
            // part is IslandoraSpreadsheetIngestIntermediateBatchObject extends IslandoraBatchObject
            foreach ($object->getSerialParts() as $part) {
              call_user_func_array(
                array(
                  $this,
                  'addToDatabase',
                ),
                array($part)
              );
              $this->total++;
            }
          }
        }
        else {
          throw new IslandoraSpreadsheetIngestBatchException('Object already exists in Islandora.');
        }
      }
      catch (Exception $e) {
        watchdog_exception('islandora_spreadsheet_ingest', $e);
        if ($e instanceof PDOException || $e instanceof IslandoraSpreadsheetIngestBatchException) {
          drupal_set_message(t('Items in the batch set are incomplete due to duplicate PIDs (@identifier). Please review existing sets and ingested objects.', array(
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
  protected $pid;

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
    // var_dump($parameters);
    $this->pid = $this->getValue(
      'pid',
      $this
        ->connection
        ->repository
        ->getNextIdentifier($this->parameters['namespace'])
    );
    // var_dump($this->pid);die("Received namespace {$this->parameters['namespace']}");
    parent::__construct($this->pid, $connection->repository);
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
    // If is a serial object and islandora_spreadsheet_ingest_serial is enabled.
    elseif ($cmodel == 'islandora:rootSerialCModel') {
      return new IslandoraSpreadsheetIngestSerialBatchObject($connection, $parameters);
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
    $xml_string = $this->transformWithSaxon($template_uri);

    // XXX. The saxon processor sorts the namespace declarations in
    // similar fashion to how PHP's sort function does.
    if ($dsid == 'MODS' && !islandora_spreadsheet_ingest_validate_namespaces($xml_string)) {
      $xml_string = islandora_spreadsheet_ingest_update_namespaces($xml_string);
    }

    $dom->loadXML($xml_string);
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
    module_load_include('inc', 'dgi_saxon_helper', 'includes/utilities');
    $this->buildParameterList();

    // Spreadsheet doesn't have an input file so open an empty one.
    $empty_input_file_pointer = fopen('php://temp', 'rb');
    $saxon_output_file_pointer = fopen('php://temp', 'r+b');
    if (!$saxon_output_file_pointer) {
      throw new IslandoraSpreadsheetIngestBatchException('Failed to open file pointer for Saxon.');
    }
    try {
      dgi_saxon_helper_transform($empty_input_file_pointer, $saxon_output_file_pointer, $template_uri, $this->xsltParameters, array('it' => 'root'));
      fseek($saxon_output_file_pointer, 0);
      return stream_get_contents($saxon_output_file_pointer);
    }
    catch (DgiSaxonHelperTransformationException $e) {
      throw new IslandoraSpreadsheetIngestBatchException($e);
    }
    finally {
      fclose($empty_input_file_pointer);
      fclose($saxon_output_file_pointer);
    }
  }

  /**
   * Builds a list of parameters to be passed to Saxon.
   */
  protected function buildParameterList() {
    foreach ($this->parameters['key_map'] as $key => $idx) {
      if (!empty($this->parameters['value_map'][$idx])) {
        $this->xsltParameters[$key] = $this->parameters['value_map'][$idx];
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
      $obj->setContentFromFile($object_location, FALSE);
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
      $ds->setContentFromFile($file->uri, FALSE);
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
    $parents = array_unique(explode(" ; ", $this->getValue('parent_object')));
    $parent_predicate = $this->getValue('parent_predicate', 'isMemberOfCollection');
    $parent_uri = $this->getValue('parent_uri', FEDORA_RELS_EXT_URI);

    // Add object to multiple parents.
    foreach ($parents as $parent) {
      if (!empty($parent)) {
        $this->relationships->add($parent_uri, $parent_predicate, $parent);
      }
      if ($parent_predicate != 'isMemberOfCollection') {
        break;
      }
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

  public function getPid() {
    return $this->pid;
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

// ########################

/**
 * Class definition for serial spreadsheet ingest objects.
 */
class IslandoraSpreadsheetIngestSerialBatchObject extends IslandoraSpreadsheetIngestBatchObject {

  // Array of batch objects i.e. the intermediates, stubs and pdfs.
  protected $serialParts;

  public function getSerialParts() {
    return (array) $this->serialParts;
  }

  public function countParts() {
    return count($this->serialParts);
  }

  public function __construct(IslandoraTuque $connection, array $parameters) {
    parent::__construct($connection, $parameters);
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');

    // Get the location of the absolute path of the serial directory object.
    $object_location = islandora_spreadsheet_ingest_normalize_binary_file_path(
      $this->parameters['binary_path'],
      $this->getValue('object_location')
    );
    // Create the serial parts and add to the array. This will be used to properly
    // update the number of objects to be ingested in the batch.
    $this->serialParts = $this->constructSerials(
      $parameters['namespace'],
      $object_location
    );
  }

  /**
   * Processes the object.
   */
  function batchProcess() {
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
    try {
      $this->addMetadata();
      $object_location = $this->getValue('object_location');
      if ($object_location) {
        $object_location = islandora_spreadsheet_ingest_normalize_binary_file_path($this->parameters['binary_path'], $object_location);
        if (!is_dir($object_location)) {
          throw new IslandoraSpreadsheetIngestBatchException('Expected a directory');
        }

        // This should create the root serial Object.
        $this->relationships->autoCommit = FALSE;
        $this->addRelationships();
        $label = $this->getValue('label');
        if ($label) {
          $this->label = $label;
        }
        $this->alter();
        $this->relationships->commitRelationships();

        // Get the serial parts created.
        $this->constructSerials($this->parameters['namespace'], $object_location);

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
   * Orchestrates construction of the serial objects.
   *
   * @param namespace
   *   The namespace to use for created serial parts.
   * @param object_location
   *   Absolute path to the serial directory.
   *
   * @return array
   *   Array of intermediate, stub and/or pdf parts.
   */
  function constructSerials($namespace, $object_location) {
    // TODO wrap this in try catch.
    // get dir structure as an array.
    // intermediate_1_Drawer_1A (Array, 1 element)
    //     section_1_Ecumenical China Study Reports and Minutes (1976 – 1980) (Array, 1 element)
    //     pdf_1_Ecumenical China Study Liaison Group in Germany (Array, 2 elements)
    //       0 (String, 8 characters ) MODS.xml
    //       1 (String, 7 characters ) OBJ.pdf
    $serial_array = $this->dirStructureToArray($object_location);

    $serial_objects = [];
    $current_intermediate = NULL;
    // iterate the array
    foreach ($serial_array as $intermediate_dir => $section_or_pdf_info) {
      // explode the keys (names) with "_" as delimiter
      // 0 => intermediate
      // 1 => sequence to use (1)
      // 2 => label
      if (!is_array($section_or_pdf_info)) {
        continue;
      }
      $intermediate_dir_parts = explode('_', $intermediate_dir);
      $type = $intermediate_dir_parts[0];
      $sequence = $intermediate_dir_parts[1];
      $label = $intermediate_dir_parts[2];

      // var_dump($label);die("<br/>\n label");

      // each key that has "intermediate" (lowercase) creates an intermediate object.
      if (strtolower($type) === 'intermediate') {
        $current_intermediate = new IslandoraSpreadsheetIngestIntermediateBatchObject(
          $this->connection,
          $this->pid, // serial root's pid.
          $namespace,
          $sequence,
          $label,
          $type
        );
        $serial_objects[] = $current_intermediate;
      }

      // interate the intermediates
      // explode keys (names) with "_" as delimiter.
      foreach ($section_or_pdf_info as $part => $contents) {
        // 0 (String, 7 characters ) section
        // 1 (String, 1 characters ) 1
        // 2 (String, 58 characters ) Ecumenical China Study Reports and Minutes (197...
        $stub_or_pdf_array = explode('_', $part);
        $stub_type = $stub_or_pdf_array[0];
        $stub_sequence = $stub_or_pdf_array[1];
        $stub_label = $stub_or_pdf_array[2];

        $stub_or_pdf = strtolower($stub_type);
        // each key that has "section" creates an "intermediate stub".
        if ($stub_or_pdf === 'section') {

          // create a stub. islandora:intermediateSerialCModelStub (ISLANDORA_SPREADSHEET_INGEST_SERIAL_STUB).
          $serial_objects[] = new IslandoraSpreadsheetIngestIntermediateBatchObject(
            $this->connection,
            $current_intermediate->getPid(), // the curent intermediate will be the parent.
            $namespace,
            $sequence,
            $stub_label,
            $stub_type
          );
        }
        // if the key has "pdf" create a "pdf object".
        elseif ($stub_or_pdf === 'pdf') {
          //absolute path.
          $pdf_path = "$object_location/$intermediate_dir/$part";
          $serial_objects[] = new IslandoraSpreadsheetIngestIntermediateBatchObject(
            $this->connection,
            $current_intermediate->getPid(), // the curent intermediate will be the parent.
            $namespace,
            $sequence,
            $stub_label,
            $stub_type,
            $pdf_path
          );
        }
      }
    }
    return $serial_objects;
  }

  /**
   * Converts a serial directory structure to an array.
   * https://stackoverflow.com/a/4987637
   *
   * @param dir
   *
   * @param listDir
   *
   */
  function dirStructureToArray($dir, $listDir = array()) {
    $listDir = array();
    if ($handler = opendir($dir)) {
      while (($sub = readdir($handler)) !== FALSE) {
        if ($sub != "." && $sub != ".." && $sub != "Thumb.db") {
          if (is_file($dir."/".$sub)) {
            $listDir[] = $sub;
          }
          elseif (is_dir($dir."/".$sub)) {
             $listDir[$sub] = $this->dirStructureToArray($dir."/".$sub);
          }
        }
      }
      closedir($handler);
    }
    return $listDir;
  }

}

class IslandoraSpreadsheetIngestIntermediateBatchObject extends IslandoraBatchObject {
  protected $connection;
  protected $parentPid;
  protected $namespace;
  protected $sequence;
  protected $pid;
  protected $dsDir;
  protected $serialType;

  /**
   * Constructor.
   */
  public function __construct(IslandoraTuque $connection, $parentPid, $namespace, $sequence, $label, $serialType, $dsDir = NULL) {
    $this->parentPid = $parentPid;
    $this->namespace = $namespace;
    $this->sequence = $sequence;
    $this->label = $label;
    $this->pid = $connection->repository->getNextIdentifier($namespace);
    $this->connection = $connection;
    $this->dsDir = $dsDir;
    $this->serialType = strtolower($serialType);
    parent::__construct($this->pid, $this->connection->repository);

    switch ($this->serialType) {
      case 'intermediate':
        // watchdog('my_module', "Doing this {$label} of type " . ISLANDORA_SPREADSHEET_INGEST_SERIAL_INTERMEDIATE, array());
        $this->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_INTERMEDIATE;
        break;
      case 'section':
        // watchdog('my_module', "Doing this {$label} of type " . ISLANDORA_SPREADSHEET_INGEST_SERIAL_STUB, array());
        $this->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_STUB;
        break;
      case 'pdf':
        // die("$dsDir ds directory");

        // watchdog('my_module', "Doing this {$label} of type " . ISLANDORA_SPREADSHEET_INGEST_SERIAL_PDF, array());
        $this->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_PDF;
        break;
    }
  }

  public function batchProcess() {
    try {
      // watchdog('my_module', "batch serial intermediate", array());
      // add the obj and mods for pdf.
      // if ($this->models == ISLANDORA_SPREADSHEET_INGEST_SERIAL_PDF) {
      watchdog('my_module', "batch - update pdf", array());
      if (!is_null($this->dsDir)) {
        $this->addDatastreams();
      }
      $this->addRelationships();
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

  public function addRelationships() {
    // intermediates need isMemberOf and sequence.
    switch ($this->serialType) {
      case 'intermediate':
        $this->relationships->add(
          FEDORA_RELS_EXT_URI,
          'isMemberOf',
          $this->parentPid, // pid of the rootSerialCModel object.
          RELS_TYPE_URI
        );
        $this->relationships->add(
          ISLANDORA_RELS_EXT_URI,
          ISLANDORA_SERIAL_OBJECT_SEQUENCE_PREDICATE,
          // use the number from the explode as the sequence
          $this->sequence,
          RELS_TYPE_INT
        );
        break;
      case 'section':
      case 'pdf':
      $this->relationships->add(
        FEDORA_RELS_EXT_URI,
        'isComponentOf',
        $this->parentPid, // pid of the rootSerialCModel object.
        RELS_TYPE_URI
      );
      $this->relationships->add(
        ISLANDORA_RELS_EXT_URI,
        ISLANDORA_SERIAL_OBJECT_SEQUENCE_PREDICATE,
        $this->sequence,
        RELS_TYPE_INT
      );
      break;
    }
  }

  /**
   * Adds arbitrary datastreams in the given directory.
   *
   * @param string $object_location
   *   The relative path to a folder where arbitrary datastreams are being held.
   */
  public function addDatastreams() {
    // if (is_null($this->dsDir)) {
    //   return;
    // }
    module_load_include('inc', 'islandora', 'includes/utilities');
    if (!is_readable($this->dsDir) || !is_dir($this->dsDir)) {
      watchdog('my_module', "cannot read dir", array());

      throw new IslandoraSpreadsheetIngestBatchException(t('Failed to find or read the path to the datastreams at @path', array(
        '@path' => $this->dsDir,
      )));
    }
    $files = file_scan_directory(
      drupal_realpath($this->dsDir),
      '/.*/',
      array('recurse' => FALSE));
    foreach ($files as $file) {
      watchdog('my_module', "add ds " . $file->name, array());
      if (!isset($this[$file->name])) {
        $ds = $this->constructDatastream($file->name, 'M');
      }
      else {
        $ds = $this[$file->name];
      }
      $ds->setContentFromFile($file->uri, FALSE);
      $ds->mimetype = file_get_mimetype($file->uri);
      $ds->label = "{$file->name} Datastream";
      if (!isset($this[$file->name])) {
        $this->ingestDatastream($ds);
      }
    }
  }

  /**
   * Get resources for this item.
   */
  public function getResources() {
    return array();
  }

  public function getPid() {
    return $this->pid;
  }
}
