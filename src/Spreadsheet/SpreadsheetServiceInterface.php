<?php

namespace Drupal\islandora_spreadsheet_ingest\Spreadsheet;

use Drupal\file\FileInterface;

/**
 * Spreadsheet service interface.
 */
interface SpreadsheetServiceInterface {

  /**
   * Read the given file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The spreadsheet file to read.
   *
   * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
   *   A spreadsheet object representing the given file.
   */
  public function read(FileInterface $file);

  /**
   * Get a reader for the given file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The spreadsheet file for which to build a reader.
   *
   * @return \PhpOffice\PhpSpreadsheet\Reader\IReader
   *   An IReader instance with which the given file might be read.
   */
  public function getReader(FileInterface $file);

  /**
   * Get the header from the given file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The spreadsheet file of which to get the header.
   * @param string|null $sheet
   *   The name of the sheet from which to scrape the header row, or NULL if the
   *   format does not support multiple sheets.
   * @param int $row
   *   Zero-indexed row which represents the header to scrape.
   *
   * @return string[]
   *   The values of the cells from the indicated header row.
   */
  public function getHeader(FileInterface $file, $sheet = NULL, $row = 0);

  /**
   * List the worksheets contained in the specified spreadsheet.
   *
   * @param \Drupal\file\FileInterface $file
   *   The spreadsheet file of which to list the worksheets.
   *
   * @return string[]|null
   *   An array of strings representing the contained worksheets, or NULL if
   *   the file is of a format which does not support multiple sheets.
   */
  public function listWorksheets(FileInterface $file);

}
