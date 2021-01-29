<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePlugin;

#use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

use Drupal\islandora_spreadsheet_ingest\Plugin\PluginBase;
use Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginInterface;

/**
 * Get plugin.
 *
 * @PipelineSourcePlugin(
 *   id = "get",
 *   label = @Translation("Get Plugin"),
 * )
 */
class Get extends PluginBase implements PipelineSourcePluginInterface {

  use StringTranslationTrait;

  public function defaultConfiguration() {
    return [
      'plugin' => $this->getPluginId(),
      'source_name' => $this->t('Source row')
    ];
  }

  public function getName() {
    return $this->configuration['source'];
  }

  public function getSourceName() {
    return $this->configuration['source_name'];
  }

  public function toProcessArray() {
    return [
      'plugin' => 'get',
      'source' => $this->getName(),
    ];
  }

  public function calculateDependencies() {
    return [];
  }

}
