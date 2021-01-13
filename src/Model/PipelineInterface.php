<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

interface PipelineInterface extends SourceInterface {

  /**
   * Get the attached source.
   *
   * @return \Drupal\islandora_spreadsheet_ingest\Model\SourceInterface
   */
  public function getSource();

  public function getDestinationName();

  public function addStep(PipelineStepInterface $step);
  public function removeStep(PipelineStepInterface $step);

  public function toPipelineArray();
}
