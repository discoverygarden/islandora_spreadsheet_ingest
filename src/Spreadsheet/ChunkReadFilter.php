<?php

namespace Drupal\islandora_spreadsheet_ingest\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Filter adapted directly from PhpSpreadsheet's documentation.
 *
 * phpcs:disable Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed,Drupal.Commenting.Deprecated.DeprecatedVersionFormat,Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat
 *
 * @deprecated in 3.11.x and is removed from 4.x. Use openspout instead.
 * @see https://github.com/discoverygarden/islandora_spreadsheet_ingest/issues/129
 */
class ChunkReadFilter implements IReadFilter {

  /**
   * The row on which to start reading.
   *
   * @var int
   */
  protected $startRow;

  /**
   * The row on which to end reading.
   *
   * @var int
   */
  protected $endRow;

  /**
   * Constructor.
   */
  public function __construct($startRow = 0, $chunkSize = 0) {
    @trigger_error('Deprecated in 3.11.x for removal in 4.x; rework to use another library, such as OpenSpout. See https://github.com/discoverygarden/islandora_spreadsheet_ingest/issues/129', E_USER_DEPRECATED);
    $this->setRows($startRow, $chunkSize);
  }

  /**
   * Set the list of rows that we want to read.
   *
   * @param int $startRow
   *   The row on which to start reading.
   * @param int $chunkSize
   *   The number of rows to read.
   */
  public function setRows($startRow, $chunkSize) {
    $this->startRow = $startRow;
    $this->endRow = $startRow + $chunkSize;
  }

  /**
   * {@inheritdoc}
   */
  public function readCell($column, $row, $worksheetName = '') {
    // Only read the heading row, and the configured rows.
    return ($row == 1) || ($row >= $this->startRow && $row < $this->endRow);
  }

}
