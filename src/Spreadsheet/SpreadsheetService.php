<?php

namespace Drupal\islandora_spreadsheet_ingest\Spreadsheet;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use OpenSpout\Reader\ODS\Reader as ODSReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Spreadsheet service.
 *
 * phpcs:disable Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
 */
class SpreadsheetService implements SpreadsheetServiceInterface {

  /**
   * Allow associating other resources to the lifecycle of readers.
   *
   * @var \WeakMap
   */
  protected \WeakMap $readers;

  /**
   * Allow associating other resources to the lifecycle of worksheets.
   *
   * @var \WeakMap
   */
  protected \WeakMap $worksheets;

  /**
   * Constructor.
   */
  public function __construct(
    protected FileSystemInterface $fileSystem,
  ) {
    $this->readers = new \WeakMap();
    $this->worksheets = new \WeakMap();
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
   * @throws \InvalidArgumentException
   *   Thrown if the given file is not stored locally.
   */
  protected function getFilePath(FileInterface $file) {
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
    @trigger_error('Deprecated in 3.11.x for removal in 4.x; rework to use another library, such as OpenSpout. See https://github.com/discoverygarden/islandora_spreadsheet_ingest/issues/129', E_USER_DEPRECATED);
    $reader = $this->getReader($file);
    return $reader->load($this->getFilePath($file));
  }

  /**
   * {@inheritdoc}
   */
  public function getReader(FileInterface $file) {
    @trigger_error('Deprecated in 3.11.x for removal in 4.x; rework to use another library, such as OpenSpout. See https://github.com/discoverygarden/islandora_spreadsheet_ingest/issues/129', E_USER_DEPRECATED);
    $reader = IOFactory::createReaderForFile($this->getFilePath($file));
    $reader->setReadDataOnly(TRUE);

    return $reader;
  }

  /**
   * Get reader for the given file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file for which to get a reader.
   *
   * @return \OpenSpout\Reader\ReaderInterface
   *   Reader for the file.
   *
   * @throws \OpenSpout\Common\Exception\IOException
   */
  private function getSpoutReader(FileInterface $file) : ReaderInterface {
    $uri = $file->getFileUri();
    $realpath = $this->fileSystem->realpath($uri);
    $extension = pathinfo($uri, PATHINFO_EXTENSION);
    /** @var \OpenSpout\Reader\ReaderInterface $reader_class */
    $reader_class = match(strtolower($extension)) {
      'csv' => CSVReader::class,
      'ods' => ODSReader::class,
      'xlsx' => XLSXReader::class,
    };
    $reader = new $reader_class();
    $this->readers[$reader] = [];

    if ($realpath !== FALSE) {
      $reader->open($realpath);
    }
    else {
      try {
        // Real-path of stream wrappers does not quite make sense, so allow
        // an opportunity for files from stream wrappers to be processed.
        $reader->open($uri);
      }
      catch (IOException) {
        // Possibly an exception such as:
        // "OpenSpout\Common\Exception\IOException: Could not open
        // {scheme}://{filename}.ods for reading! Stream wrapper used is not
        // supported for this type of file."
        // So let's try to spool to a temp file to remove the stream wrapper
        // from the equation.
        $spooled = $this->fileSystem->copy($uri, sys_get_temp_dir());
        $spool_file = $this->fileSystem->realpath($spooled);
        $this->readers[$reader][] = new SelfDestructingFile($spool_file);
        $reader->open($spool_file);
      }
    }

    return $reader;
  }

  /**
   * Helper; get given worksheet with (Open)Spout.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file of which to obtain a worksheet.
   * @param string|null $sheet_name
   *   The name of the worksheet. Note that not all formats (such as CSV) deal
   *   with named worksheets.
   *
   * @return \OpenSpout\Reader\SheetInterface
   *   The requested sheet.
   *
   * @throws \OpenSpout\Common\Exception\IOException
   * @throws \OpenSpout\Reader\Exception\ReaderNotOpenedException
   */
  private function getSpoutWorksheet(FileInterface $file, ?string $sheet_name = NULL) : SheetInterface {
    $reader = $this->getSpoutReader($file);

    if ($reader instanceof CSVReader) {
      foreach ($reader->getSheetIterator() as $sheet) {
        $this->worksheets[$sheet] = [$reader];
        return $sheet;
      }
    }

    foreach ($reader->getSheetIterator() as $sheet) {
      if ($sheet->getName() === $sheet_name) {
        $this->worksheets[$sheet] = [$reader];
        return $sheet;
      }
    }

    throw new \OutOfBoundsException("Failed to find worksheet of the name '$sheet_name'.");
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader(FileInterface $file, $sheet = NULL, $row = 0) {
    foreach ($this->getSpoutWorksheet($file, $sheet)->getRowIterator() as $index => $spout_row) {
      if ($index === $row) {
        return $spout_row->toArray();
      }
    }

    throw new \Exception('Failed to read header.');
  }

  /**
   * {@inheritdoc}
   */
  public function listWorksheets(FileInterface $file) {
    $reader = $this->getSpoutReader($file);

    return match(TRUE) {
      $reader instanceof CSVReader => NULL,
      default => $this->listSpoutSheets($reader),
    };
  }

  /**
   * Helper; get the listing of sheets using (Open)Spout.
   *
   * @param \OpenSpout\Reader\ReaderInterface $reader
   *   The reader to use to list the sheets.
   *
   * @return string[]
   *   The list of sheets.
   *
   * @throws \OpenSpout\Reader\Exception\ReaderNotOpenedException
   */
  private function listSpoutSheets(ReaderInterface $reader) : array {
    $sheet_names = [];

    foreach ($reader->getSheetIterator() as $sheet) {
      $sheet_names[] = $sheet->getName();
    }

    return $sheet_names;
  }

}
