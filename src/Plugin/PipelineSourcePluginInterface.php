<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\islandora_spreadsheet_ingest\Model\SourceInterface;

/**
 * Defines an interface for Pipeline Source Plugin plugins.
 */
interface PipelineSourcePluginInterface extends PluginInspectionInterface, SourceInterface {
  // Nothing specific here (presently), just combining two interfaces.
}
