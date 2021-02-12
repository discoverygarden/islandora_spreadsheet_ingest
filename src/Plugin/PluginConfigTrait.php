<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin;

/**
 * Some non-validating implementations for ConfigurableInterface.
 */
trait PluginConfigTrait {

  /**
   * Naively fetch the config.
   *
   * @see \Drupal\Component\Plugin\ConfigurableInterface::getConfiguration()
   *
   * @return array
   *   The configuration.
   */
  public function getConfiguration() {
    assert(is_array($this->configuration));
    return $this->configuration;
  }

  /**
   * Naively set the config.
   *
   * @see \Drupal\Component\Plugin\ConfigurableInterface::setConfiguration()
   */
  public function setConfiguration(array $config) {
    assert(is_array($this->configuration));
    $this->configuration = $config;
  }

}
