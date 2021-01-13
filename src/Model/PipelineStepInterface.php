<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

interface PipelineStepInterface {
  public function toProcessArray();
}
