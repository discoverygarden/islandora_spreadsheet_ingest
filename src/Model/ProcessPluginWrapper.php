<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

/**
 * Wrapper for process plugins.
 */
class ProcessPluginWrapper implements PipelineStepInterface {

  /**
   * The configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Constructor.
   */
  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function toProcessArray() {
    return $this->configuration;
  }

}
