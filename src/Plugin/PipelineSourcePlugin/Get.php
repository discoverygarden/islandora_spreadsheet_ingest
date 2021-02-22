<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePlugin;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Plugin\ConfigurableInterface;

use Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginBase;
use Drupal\islandora_spreadsheet_ingest\Plugin\PluginConfigTrait;

/**
 * Get plugin; handle accessing an item from the input row.
 *
 * @PipelineSourcePlugin(
 *   id = "get",
 *   label = @Translation("Get Plugin"),
 * )
 */
class Get extends PipelineSourcePluginBase implements ConfigurableInterface {

  use StringTranslationTrait;
  use PluginConfigTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'plugin' => $this->getPluginId(),
      'source_name' => $this->t('Source row'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->configuration['source'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceName() {
    return $this->configuration['source_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function toProcessArray() {
    return [
      'plugin' => 'get',
      'source' => $this->getName(),
    ];
  }

}
