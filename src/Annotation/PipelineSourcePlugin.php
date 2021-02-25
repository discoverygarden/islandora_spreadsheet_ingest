<?php

namespace Drupal\islandora_spreadsheet_ingest\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Pipeline Source Plugin item annotation object.
 *
 * @see \Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class PipelineSourcePlugin extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
