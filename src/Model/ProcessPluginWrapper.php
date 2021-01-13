<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\migrate\Plugin\MigrateProcessInterface;

class ProcessPluginWrapper implements PipelineStepInterface {
  protected $configuration;

  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  public function toProcessArray() {
    return $this->configuration;
  }
}
