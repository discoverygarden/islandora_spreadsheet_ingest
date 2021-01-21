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

  public static function createFromConfig($config, $source_name, array $entries) {
    $name = $config['source'];
    foreach (['@@@', '@'] as $prefix) {
      if (strpos($name, $prefix) === 0) {
        $_name = substr($name, strlen($prefix));
        if (isset($entries[$_name])) {
          return $entries[$_name];
        }
      }
    }

    return new static($name, $source_name);
  }
}
