<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\migrate\Plugin\MigrateProcessInterface;

class ProcessSourcePluginWrapper extends ProcessPluginWrapper implements SourceInterface {
  public function getName() {
    return var_export($this->configuration, TRUE);
  }
}
