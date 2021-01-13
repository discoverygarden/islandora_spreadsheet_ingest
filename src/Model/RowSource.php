<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

class RowSource implements SourceInterface {

  protected $name;

  public function __construct($name) {
    $this->name = $name;
  }

  public function getName() {
    return $this->name;
  }

  public function toProcessArray() {
    return [
      'plugin' => 'get',
      'source' => $this->getName(),
    ];
  }
}
