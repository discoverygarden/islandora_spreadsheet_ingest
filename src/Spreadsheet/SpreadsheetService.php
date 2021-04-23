<?php

namespace Drupal\islandora_spreadsheet_ingest\Spreadsheet;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Drupal\file\FileInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Spreadsheet service.
 */
class SpreadsheetService implements SpreadsheetServiceInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructor.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * Helper to get the real path of the given file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file for which to determine the real path.
   *
   * @return string
   *   The real path of the file
   *
   * @throws \InvalidArgumentException if the given file is not stored locally.
   */
  protected getFilePath(FileInterface $file) {
    $path = $this->fileSystem->realpath($file->getFileUri());
    if ($path) {
      return $path;
    }
    else {
      throw new \InvalidArgumentException('The file must be local in order to be parsed.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function read(FileInterface $file) {
    $reader = $this->getReader($file);
    return $reader->load($this->getFilePath($file));
  }

  /**
   * {@inheritdoc}
   */
  public function getReader(FileInterface $file) {
    $reader = IOFactory::createReaderForFile($this->getFilePath($file));
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
    $reader->setReadFilter(new ChunkReadFilter($row, $row + 1));
    $loaded = $reader->load($this->getFilePath($file));

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
      return call_user_func($lister, $this->getFilePath($file));
    }
  }

}
