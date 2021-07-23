<?php

/**
 * Class definition for serial spreadsheet ingest objects.
 */
class IslandoraSpreadsheetIngestSerialBatchObject extends IslandoraSpreadsheetIngestBatchObject {

  // protected $namespace;
  // protected $serial_root_pid;

  // public function __construct(IslandoraTuque $connection, array $parameters) {
  //   parent::__construct($connection, $parameters);
  //   // $this->serial_root_pid = $serial_root_pid;
  //   // watchdog('my_module', "params namespace: " . $this->parameters['namespace'], array());
  //   // watchdog('my_module', "parent pid: " . $this->pid, array());
  //   // var_dump($this->parameters['namespace']);
  //   // die();
  //
  //   // module_load_include('inc', 'islandora', 'includes/utilities');
  //   // $namespace = islandora_get_namespace($this->pid);
  // }

  /**
   * Processes the object.
   */
  function batchProcess() {
    module_load_include('inc', 'islandora_spreadsheet_ingest', 'includes/utilities');
    dd('batch process serials');
    watchdog('my_module', 'batch process serials', array());
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
        dd("Done with the serial root, lets create the parts");
        watchdog('my_module', "Done with the serial root, lets create the parts", array());
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
   * Orchestrats construction of the serial objects.
   *
   * @param namespace
   *   The namespace to use for created serial parts.
   */
  function constructSerials($namespace, $object_location) {
    //TODO wrap this in try catch.
    dd('lets create the serial parts');
    watchdog('my_module', "lets create the serial parts", array());
    // ISLANDORA_SPREADSHEET_INGST_SERIAL_ROOT = 'islandora:rootSerialCModel';
    // ISLANDORA_SPREADSHEET_INGST_SERIAL_INTERMEDIATE = 'islandora:intermediateCModel';
    // const ISLANDORA_SPREADSHEET_INGST_SERIAL_STUB = 'islandora:intermediateSerialCModelStub';

    // get dir structure as an array.
    // intermediate_1_Drawer_1A (Array, 1 element)
    //     section_1_Ecumenical China Study Reports and Minutes (1976 – 1980) (Array, 1 element)
    //         pdf_1_Ecumenical China Study Liaison Group in Germany (Array, 2 elements)
    //             0 (String, 8 characters ) MODS.xml
    //             1 (String, 7 characters ) OBJ.pdf
    $serial_array = $this->dirStructureToArray($object_location);

    $intermediate_objects = [];
    $section_objects = [];
    $pdfs = [];
    // iterate the array
    foreach ($serial_array as $intermediate_dir => $section_or_pdf_info) {
      // explode the keys (names) with "_" as delimiter
      // 0 => intermediate
      // 1 => sequence to use (1)
      // 2 => label
      $intermediate_dir_parts = explode('_', $intermediate_dir);
      $type = $intermediate_dir_parts[0];
      $sequence = $intermediate_dir_parts[1];
      $label = $intermediate_dir_parts[2];

      // each key that has "intermediate" (lowercase) creates an intermediate object
      if (strtolower($type) === 'intermediate') {
        $intermediate_pid = $this->connection->repository->getNextIdentifier($namespace);
        $intermediate_object = $this->connection->repository->constructObject($intermediate_pid);
        // use the remaining text as the label
        $intermediate_object->label = $label;
        $intermediate_object->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_INTERMEDIATE;
        // intermediates need isMemberOf and sequence.
        $intermediate_object->relationships->add(
          FEDORA_RELS_EXT_URI,
          'isMemberOf',
          $this->pid,
          RELS_TYPE_URI
        );
        $intermediate_object->relationships->add(
          ISLANDORA_RELS_EXT_URI,
          ISLANDORA_SERIAL_OBJECT_SEQUENCE_PREDICATE,
          // use the number from the explode as the sequence
          $sequence,
          RELS_TYPE_INT
        );
        // add to an array with a pid.
        // $intermediate_objects[$intermediate_pid] = $intermediate_object;
        // Just ingest it.
        $this->connection->repository->ingestObject($intermediate_object);
      }
      dd('created an intermediate with pid ' . $intermediate_pid);
      watchdog('my_module', "created an intermediate with pid $intermediate_pid", array());


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
          dd("lets create a stub");
          watchdog('my_module', "lets create a stub", array());

          // create a stub. islandora:intermediateSerialCModelStub (ISLANDORA_SPREADSHEET_INGEST_SERIAL_STUB).
          $stub_pid = $this->connection->repository->getNextIdentifier($namespace);
          $stub_object = $this->connection->repository->constructObject($stub_pid);
          // use the remaining text as the label.
          $stub_object->label = $stub_label;
          $stub_object->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_STUB;
          // add isComponentOf
          $stub_object->relationships->add(
            ISLANDORA_RELS_EXT_URI,
            ISLANDORA_SERIAL_OBJECT_PDF_PARENT_PREDICATE,
            $intermediate_pid
          );
          $stub_object->relationships->add(
            ISLANDORA_RELS_EXT_URI,
            ISLANDORA_SERIAL_OBJECT_SEQUENCE_PREDICATE,
            // use the number from the explode as the sequence
            $stub_sequence,
            RELS_TYPE_INT
          );
          // $section_objects[$stub_pid] = $stub_object;
          $this->connection->repository->ingestObject($stub_object);
          dd("created a stub with pid $stub_pid");
          watchdog('my_module', "created a stub with pid $stub_pid", array());

          // if a section, loop that for a pdf
          if (is_array($contents) && count($contents) > 0) {
            // Should be a pdf directory so explode the name.
            foreach ($contents as $info => $content) {
              $info_array = explode('_', $info);
              $info_type = $info_array[0];
              $info_sequence = $info_array[1];
              $info_label = $info_array[2];
              module_load_include('inc', 'islandora_serial_object', 'includes/utilities');
              $sequence = islandora_serial_object_get_max_sequence_number($intermediate_pid);

              // create a pdf.
              $pdf_pid = $this->connection->repository->getNextIdentifier($namespace);
              $pdf_object = $this->connection->repository->constructObject($pdf_pid);
              // use the remaining text as the label
              $pdf_object->label = $info_label;
              $pdf_object->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_PDF;
              // add isComponentOf
              $pdf_object->relationships->add(
                ISLANDORA_RELS_EXT_URI,
                ISLANDORA_SERIAL_OBJECT_PDF_PARENT_PREDICATE,
                $intermediate_pid
              );
              $pdf_object->relationships->add(
                ISLANDORA_RELS_EXT_URI,
                ISLANDORA_SERIAL_OBJECT_SEQUENCE_PREDICATE,
                // use the number from the explode as the sequence
                $sequence,
                RELS_TYPE_INT
              );
              // $pdfs[$pdf_pid] = $pdf_object;
              $this->connection->repository->ingestObject($pdf_object);
              // assign contents appropriately.
                // use $this->addDatastreams maybe?
            }
          }
        }
        // if the key has "pdf" create a "pdf object".
        elseif ($stub_or_pdf === 'pdf') {
          dd("lets create a pdf");
          watchdog('my_module', "lets create a pdf", array());
          // create a pdf.
          $pdf_pid = $this->connection->repository->getNextIdentifier($namespace);
          $pdf_object = $this->connection->repository->constructObject($pdf_pid);
          // use the remaining text as the label
          $pdf_object->label = $stub_label;
          $pdf_object->models = ISLANDORA_SPREADSHEET_INGEST_SERIAL_PDF;
          // add isComponentOf
          $pdf_object->relationships->add(
            ISLANDORA_RELS_EXT_URI,
            ISLANDORA_SERIAL_OBJECT_PDF_PARENT_PREDICATE,
            $intermediate_pid
          );
          $pdf_object->relationships->add(
            ISLANDORA_RELS_EXT_URI,
            ISLANDORA_SERIAL_OBJECT_SEQUENCE_PREDICATE,
            // use the number from the explode as the sequence
            $stub_sequence,
            RELS_TYPE_INT
          );
          $pdfs[$pdf_pid] = $pdf_object;
          $this->connection->repository->ingestObject($pdf_object);
          dd("created a pdf with pid $pdf_pid");
          watchdog('my_module', "created a pdf with pid $pdf_pid", array());
        }
      }
    }

    // foreach ($intermediate_objects as $obj) {
    //   $this->connection->repository->ingestObject($obj);
    // }
    // foreach($section_objects as $obj) {
    //   $this->connection->repository->ingestObject($obj);
    // }
    // foreach($pdfs as $obj) {
    //   $this->connection->repository->ingestObject($obj);
    // }
  }

  /**
   * Converts a serial directory structure to an array.
   * https://stackoverflow.com/a/4987637
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
