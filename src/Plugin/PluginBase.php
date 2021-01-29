<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin;

use Drupal\Component\Plugin\PluginBase as UpstreamPluginBase;
use Drupal\Component\Plugin\ConfigurablePluginInterface;

abstract class PluginBase extends UpstreamPluginBase implements ConfigurablePluginInterface {

  public function getConfiguration() {
    return $this->configuration;
  }

  public function setConfiguration(array $config) {
    $this->configuration = $config;
  }

}
