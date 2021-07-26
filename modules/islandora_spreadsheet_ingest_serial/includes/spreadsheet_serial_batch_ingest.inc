<?php


class IslandoraSpreadsheetIngestIntermediateBatchObject extends IslandoraBatchObject {
  protected $connection;
  protected $parentPid;
  protected $namespace;
  protected $sequence;
  public $label;
  protected $pid;
  // protected $model;

  /**
   * Constructor.
   */
  public function __construct(IslandoraTuque $connection, $parentPid, $namespace, $sequence, $label, $serialType) {
    $this->parentPid = $parentPid;
    $this->namespace = $namespace;
    $this->sequence = $sequence;
    $this->label = $label;
    $this->pid = $connection->repository->getNextIdentifier($namespace);
    $this->connection = $connection;
    parent::__construct($this->pid, $this->connection->repository);

    watchdog('my_module', "Doing this $label", array());

    switch (strtolower($serialType)) {
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

  public function batchProcess() {
    // add the obj and mods for pdf
    if ($this->models == ISLANDORA_SPREADSHEET_INGEST_SERIAL_PDF) {
      watchdog('my_module', "batch - update pdf", array());
    }
  }

  public function addRelationships() {
    // intermediates need isMemberOf and sequence.
    switch (strtolower($serialType)) {
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
        // use the number from the explode as the sequence
        $this->sequence,
        RELS_TYPE_INT
      );
      break;
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
