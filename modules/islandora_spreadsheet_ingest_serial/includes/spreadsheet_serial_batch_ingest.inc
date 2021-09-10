<?php

/**
 * @file
 * Spreadsheet batch ingest classes of serial objects.
 */

/**
 * Class definition for serial spreadsheet ingest objects.
 */
class IslandoraSpreadsheetIngestSerialBatchObject extends IslandoraSpreadsheetIngestBatchObject {

  /**
   * An array of created IslandoraSpreadsheetIngestIntermediateBatchObjects.
   *
   * @var IslandoraSpreadsheetIngestIntermediateBatchObject[]
   *   These objects can be the intermediates, stubs and pdfs.
   */
  private $serialParts;

  public function __construct(IslandoraTuque $connection, array $parameters) {
    parent::__construct($connection, $parameters);
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');

    // Get the location of the absolute path of the serial directory object.
    $object_location = islandora_spreadsheet_ingest_normalize_binary_file_path(
      $this->parameters['binary_path'],
      $this->getValue('object_location')
    );
    // Create the serial parts and add to the array. This will be used to
    // properly update the number of objects to be ingested in the batch.
    $this->setSerialParts($parameters['namespace'], $object_location);
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
        $object_location = islandora_spreadsheet_ingest_normalize_binary_file_path(
          $this->parameters['binary_path'],
          $object_location
        );
        if (!is_dir($object_location)) {
          throw new IslandoraSpreadsheetIngestBatchException('Expected a directory');
        }
        $this->addDatastreams($object_location);

        // This should create the root serial Object.
        $this->relationships->autoCommit = FALSE;
        $this->addRelationships();
        $label = $this->getValue('label');
        if ($label) {
          $this->label = $label;
        }
        $this->alter();
        $this->relationships->commitRelationships();
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
   * Orchestrates construction of the serial objects and sets serialParts array.
   *
   * @param namespace
   *   The namespace to use for created serial parts.
   * @param object_location
   *   Absolute path to the serial directory.
   *
   * @return array
   *   Array of intermediate, stub and/or pdf parts.
   */
  function setSerialParts($namespace, $object_location) {
    // TODO wrap this in try catch.
    // Get dir structure as an array:
    // intermediate_1_{root_serial_object_label}
    //     section_1_{section_object_label}
    //     pdf_1_{pdf_object_label}
    //       MODS.xml
    //       OBJ.pdf.
    $serial_array = $this->dirStructureToArray($object_location);

    $this->serialParts = [];
    $current_intermediate = NULL;
    foreach ($serial_array as $intermediate_dir => $section_or_pdf_info) {
      // Explode the keys (names) with "_" as delimiter to get the following
      // array structure:
      // 0 => intermediate
      // 1 => sequence to use (1)
      // 2 => label.
      if (!is_array($section_or_pdf_info)) {
        continue;
      }
      $intermediate_dir_parts = explode('_', $intermediate_dir);
      $type = $intermediate_dir_parts[0];
      $sequence = $intermediate_dir_parts[1];
      $label = $intermediate_dir_parts[2];

      if (strtolower($type) === 'intermediate') {
        try {
          $current_intermediate = new IslandoraSpreadsheetIngestIntermediateBatchObject(
            $this->connection,
            $this->pid,
            $namespace,
            $sequence,
            $label,
            $type,
            "$object_location/$intermediate_dir"
          );
          $this->serialParts[] = $current_intermediate;
        }
        catch (Exception $e) {
          throw $e;
        }
      }

      // Iterate the intermediates.
      foreach ($section_or_pdf_info as $part => $contents) {
        if (!is_array($contents)) {
          continue;
        }
        $stub_or_pdf_array = explode('_', $part);
        $stub_type = $stub_or_pdf_array[0];
        $stub_sequence = $stub_or_pdf_array[1];
        $stub_label = $stub_or_pdf_array[2];

        $stub_or_pdf = strtolower($stub_type);
        // Each key that has "section" creates an "intermediate stub".
        if ($stub_or_pdf === 'section') {
          try {
            // Create a stub islandora:intermediateSerialCModelStub
            // (ISLANDORA_SPREADSHEET_INGEST_SERIAL_STUB).
            $this->serialParts[] = new IslandoraSpreadsheetIngestIntermediateBatchObject(
              $this->connection,
              $current_intermediate->getPid(),
              $namespace,
              $stub_sequence,
              $stub_label,
              $stub_type
            );
          }
          catch (Exception $e) {
            throw $e;
          }
        }
        elseif ($stub_or_pdf === 'pdf') {
          $pdf_path = "$object_location/$intermediate_dir/$part";
          try {
            $this->serialParts[] = new IslandoraSpreadsheetIngestIntermediateBatchObject(
              $this->connection,
              $current_intermediate->getPid(),
              $namespace,
              $stub_sequence,
              $stub_label,
              $stub_type,
              $pdf_path
            );
          }
          catch (Exception $e) {
            throw $e;
          }
        }
      }
    }
  }

  /**
   * Converts a serial directory structure to an array.
   *
   * Adapted from https://stackoverflow.com/a/4987637.
   *
   * @param dir
   *   The directory to recursively convert an array.
   *
   * @return array
   *   An associative array representing the directory structure.
   */
  function dirStructureToArray($dir) {
    $listDir = array();
    if ($handler = opendir($dir)) {
      while (($sub = readdir($handler)) !== FALSE) {
        if ($sub != "." && $sub != "..") {
          if (is_file("$dir/$sub")) {
            $listDir[] = $sub;
          }
          elseif (is_dir($dir."/".$sub)) {
            $listDir[$sub] = $this->dirStructureToArray("$dir/$sub");
          }
        }
      }
      closedir($handler);
    }
    return $listDir;
  }

  /**
   * Returns the array of serial parts.
   *
   * @return IslandoraSpreadsheetIngestIntermediateBatchObject[]
   *   An array of IslandoraSpreadsheetIngestIntermediateBatchObjects.
   */
  public function getSerialParts() {
    return (array) $this->serialParts;
  }

  /**
   * Returns a count of the array of serial parts.
   *
   * @return int
   *   A count of the objects in the serialParts array.
   */
  public function countParts() {
    return count($this->serialParts);
  }

}

/**
 * Class definition for serial intermediate, stub and pdf objects.
 */
class IslandoraSpreadsheetIngestIntermediateBatchObject extends IslandoraBatchObject {
  protected $connection;
  protected $parentPid;
  protected $namespace;
  protected $sequence;
  protected $pid;
  protected $dsDir;
  protected $serialType;
  private $tmpLabel;

  /**
   * Constructor.
   */
  public function __construct(IslandoraTuque $connection, $parentPid, $namespace, $sequence, $label, $serialType, $dsDir = NULL) {
    $this->parentPid = $parentPid;
    $this->namespace = $namespace;
    $this->sequence = $sequence;
    $this->pid = $connection->repository->getNextIdentifier($namespace);
    $this->connection = $connection;
    $this->dsDir = $dsDir;
    $this->tmpLabel = $label;
    $this->serialType = strtolower($serialType);
    parent::__construct($this->pid, $this->connection->repository);

    switch ($this->serialType) {
      case 'intermediate':
        $this->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_INTERMEDIATE;
        break;
      case 'section':
        $this->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_STUB;
        break;
      case 'pdf':
        $this->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_PDF;
        break;
    }
  }

  /**
   * Processes the object.
   */
  public function batchProcess() {
    try {
      $this->label = $this->tmpLabel;
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

  /**
   * Adds appropriate relationships to the object base on it type.
   */
  public function addRelationships() {
    switch ($this->serialType) {
      case 'intermediate':
        $this->relationships->add(
          FEDORA_RELS_EXT_URI,
          'isMemberOf',
          $this->parentPid,
          RELS_TYPE_URI
        );
        $this->relationships->add(
          ISLANDORA_RELS_EXT_URI,
          ISLANDORA_SERIAL_OBJECT_SEQUENCE_PREDICATE,
          $this->sequence,
          RELS_TYPE_INT
        );
        break;
      case 'section':
      case 'pdf':
      $this->relationships->add(
        ISLANDORA_RELS_EXT_URI,
        ISLANDORA_SERIAL_OBJECT_PDF_PARENT_PREDICATE,
        $this->parentPid,
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
   *   The absolute path to a folder where arbitrary datastreams are being held.
   *
   * @throws IslandoraSpreadsheetIngestBatchException
   */
  public function addDatastreams() {
    module_load_include('inc', 'islandora', 'includes/utilities');
    if (!is_readable($this->dsDir) || !is_dir($this->dsDir)) {
      throw new IslandoraSpreadsheetIngestBatchException(t('Failed to find or read the path to the datastreams at @path', array(
        '@path' => $this->dsDir,
      )));
    }

    $files = file_scan_directory(
      drupal_realpath($this->dsDir),
      '/.*/',
      array('recurse' => FALSE)
    );

    foreach ($files as $file) {
      try {
        if (!is_dir($file->uri)) {
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
      catch (Exception $e) {
        throw new IslandoraSpreadsheetIngestBatchException(t('Failed to add/create the datastream @ds', array(
          '@ds' => $file->name,
        )));
      }
    }
  }

  /**
   * Get resources for this item.
   */
  public function getResources() {
    return array();
  }

  /**
   *  Getter for the PID of this object.
   *
   * @return string
   *   Returns the pid of this object.
   */
  public function getPid() {
    return $this->pid;
  }
}
