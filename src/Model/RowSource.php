<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

class RowSource implements SourceInterface {

  protected $name;
  protected $sourceName;

  public function __construct($name, $source_name) {
    $this->name = $name;
    $this->sourceName = $source_name;
  }

  public function getName() {
    return $this->name;
  }

  public function getSourceName() {
    return $this->sourceName;
  }

  public function toProcessArray() {
    return [
      'plugin' => 'get',
      'source' => $this->getName(),
    ];
  }
}
