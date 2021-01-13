<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

class GetStep implements PipelineStepInterface {
  protected $source;

  public function __construct(SourceInterface $source) {
    $this->source = $source;
  }

  public function toArray() {
    return [
      'plugin' => 'get',
      'source' => $this->source->getName(),
    ];
  }
}
