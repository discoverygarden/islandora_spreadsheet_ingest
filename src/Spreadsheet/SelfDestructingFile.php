<?php

namespace Drupal\islandora_spreadsheet_ingest\src\Spreadsheet;

/**
 * File that deletes itself when garbage collected.
 *
 * @internal
 */
class SelfDestructingFile {

  /**
   * Constructor.
   */
  public function __construct(
    protected string $filename,
  ) {}

  /**
   * Destructor.
   */
  public function __destruct() {
    unlink($this->filename);
  }

}
