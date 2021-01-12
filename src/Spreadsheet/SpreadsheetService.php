<?php

namespace Drupal\islandora_spreadsheet_ingest\Spreadsheet;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Drupal\file\FileInterface;

/**
 * Spreadsheet service.
 */
class SpreadsheetService implements SpreadsheetServiceInterface {

  /**
   * {@inheritdoc}
   */
  public function read(FileInterface $file) {
    $reader = $this->getReader($file);
    return $reader->load($file->getFileUri());
  }

  /**
   * {@inheritdoc}
   */
  public function getReader(FileInterface $file) {
    $uri = $file->getFileUri();

    $reader = IOFactory::createReaderForFile($uri);
    $reader->setReadDataOnly(TRUE);

    return $reader;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader(FileInterface $file, $sheet = NULL, $row = 0) {
    $reader = $this->getReader($file);

    if ($sheet) {
      $reader->setLoadSheetsOnly($sheet);
    }
    $filter = new ChunkReadFilter($row, $row + 1);
    $loaded = $reader->load($file->getFileUri());

    foreach ($loaded->getActiveSheet()->getRowIterator() as $row) {
      $cell_iterator = $row->getCellIterator();
      $cell_iterator->setIterateOnlyExistingCells(FALSE);

      return array_map([static::class, 'mapCellToValue'], iterator_to_array($cell_iterator));
    }

    throw new Exception('Failed to read header.');
  }

  /**
   * Get the value of a cell.
   *
   * @param \PhpOffice\PhpSpreadsheet\Cell\Cell $cell
   *   The cell of which to get the value.
   *
   * @return mixed
   *   The value of the cell.
   */
  protected static function mapCellToValue(Cell $cell) {
    return $cell->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function listWorksheets(FileInterface $file) {
    $reader = $this->getReader($file);
    $lister = [$reader, 'listWorksheetNames'];
    if (is_callable($lister)) {
      return call_user_func($lister, $file->getFileUri());
    }
  }

}
