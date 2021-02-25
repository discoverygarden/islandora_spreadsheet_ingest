<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

/**
 * Field/property pipeline representation.
 */
interface PipelineInterface extends SourceInterface {

  /**
   * Get the attached source.
   *
   * @return \Drupal\islandora_spreadsheet_ingest\Model\SourceInterface
   *   The source value from which to start processing.
   */
  public function getSource();

  /**
   * Get the destintation field/property for the output of this pipeline.
   *
   * @return string
   *   The destination field/property.
   */
  public function getDestinationName();

  /**
   * Add the step to this pipeline.
   *
   * @param Drupal\islandora_spreadsheet_ingest\Model\PipelineStepInterface $step
   *   The step to add to the pipeline.
   */
  public function addStep(PipelineStepInterface $step);

  /**
   * Remove a step from this pipeline.
   *
   * @param Drupal\islandora_spreadsheet_ingest\Model\PipelineStepInterface $step
   *   The step to remove from the pipeline.
   */
  public function removeStep(PipelineStepInterface $step);

  /**
   * Get the migration process plugin definitions representing the pipeline.
   *
   * @return array
   *   An array of migration process plugin definitions to generate the given
   *   destination.
   */
  public function toPipelineArray();

}
