<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePlugin;

use Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginBase;

/**
 * Plugin wrapper for "Source" plugins.
 *
 * @PipelineSourcePlugin(
 *   id = "wrapper",
 *   label = @Translation("Plugin wrapper"),
 * )
 */
class Wrapper extends PipelineSourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return var_export($this->configuration, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceName() {
    return $this->t('Wrapped');
  }

  /**
   * {@inheritdoc}
   */
  public function toProcessArray() {
    return $this->configuration;
  }

}
