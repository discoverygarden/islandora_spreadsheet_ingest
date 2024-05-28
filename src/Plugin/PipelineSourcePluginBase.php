<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for Pipeline Source Plugin plugins.
 */
abstract class PipelineSourcePluginBase extends PluginBase implements PipelineSourcePluginInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

}
