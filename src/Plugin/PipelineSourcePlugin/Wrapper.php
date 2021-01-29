<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePlugin;

use Drupal\Component\Plugin\PluginBase;

use Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginInterface;

/**
 * "Source" plugin wrapper.
 *
 * @PipelineSourcePlugin(
 *   id = "wrapper",
 *   label = @Translation("Plugin wrapper"),
 * )
 */
class Wrapper extends PluginBase implements PipelineSourcePluginInterface {
  public function getName() {
    return var_export($this->configuration, TRUE);
  }

  public function getSourceName() {
    return t('Wrapped');
  }

  public function toProcessArray() {
    return $this->configuration;
  }
}
