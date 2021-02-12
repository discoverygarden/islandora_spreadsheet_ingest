<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

/**
 * Data source interface.
 */
interface SourceInterface extends PipelineStepInterface {

  /**
   * Something approximating a "label".
   *
   * Just a string to show in the UI where this thing is used...
   *
   * @return string
   *   The content.
   */
  public function getName();

  /**
   * Something approximating a "category".
   *
   * Really, just used to populate the "options" dialog.
   *
   * @todo Consider pulling this out into a property on whatever plugins?
   *
   * @return string
   *   The content.
   */
  public function getSourceName();

}
