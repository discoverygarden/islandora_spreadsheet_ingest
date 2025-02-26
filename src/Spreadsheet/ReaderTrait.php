<?php

namespace Drupal\islandora_spreadsheet_ingest\Spreadsheet;

use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use OpenSpout\Reader\ODS\Reader as ODSReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;

trait ReaderTrait {

  protected static function getReader(string $path, bool $open = TRUE) : ReaderInterface {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    $reader = match ($extension) {
      'csv' => new CSVReader(),
      'xlsx' => new XLSXReader(),
      'ods' => new ODSReader(),
      default => throw new UnsupportedTypeException('No readers supporting the given type: '.$extension),
    };

    if ($open) {
      $reader->open($path);
    }

    return $reader;
  }

  protected static function getWorksheets(string $path) : ?array {
    $reader = static::getReader($path, FALSE);

    if ($reader instanceof CSVReader) {
      return NULL;
    }

    try {
      $reader->open($path);
      $to_return = [];

      /** @var \OpenSpout\Reader\SheetInterface $sheet */
      foreach ($reader->getSheetIterator() as $sheet) {
        $to_return[] = $sheet->getName();
      }

      return $to_return;
    }
    finally {
      $reader->close();
    }
  }

  protected static function getWorksheet(ReaderInterface $reader, ?string $sheet_name) : SheetInterface {
    if ($reader instanceof CSVReader) {
      foreach ($reader->getSheetIterator() as $sheet) {
        return $sheet;
      }
    }

    foreach ($reader->getSheetIterator() as $sheet) {
      if ($sheet->getName() === $sheet_name) {
        return $sheet;
      }
    }

    throw new \OutOfRangeException("Could not find sheet of the name $sheet_name");
  }

  protected static function getHeader(string $path, ?string $sheet_name = NULL, int $offset = 0) : ?array {
    try {
      $reader = static::getReader($path);
      $sheet = static::getWorksheet($reader, $sheet_name);
      foreach ($sheet->getRowIterator() as $index => $row) {
        if ($index === $offset) {
          return $row->toArray();
        }
      }
    }
    finally {
      $reader?->close();
    }
  }

}
