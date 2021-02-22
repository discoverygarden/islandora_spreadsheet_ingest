<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for Pipeline Source Plugin plugins.
 */
abstract class PipelineSourcePluginBase extends PluginBase implements PipelineSourcePluginInterface {
  use DependencySerializationTrait;
  use StringTranslationTrait;
}
