<?php

namespace Drupal\islandora_spreadsheet_ingest\Spreadsheet;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\file\FileInterface;

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

    $header = [];

    foreach ($loaded->getActiveSheet()->getRowIterator() as $row) {
      $cell_iterator = $row->getCellIterator();
      $cell_iterator->setIterateOnlyExistingCells(FALSE);

      foreach ($cell_iterator as $cell) {
        $header[] = $cell->getValue();
      }
    }

    return $header;

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
