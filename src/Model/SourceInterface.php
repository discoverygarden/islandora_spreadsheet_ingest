<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

interface SourceInterface extends PipelineStepInterface {
  public function getName();
}
