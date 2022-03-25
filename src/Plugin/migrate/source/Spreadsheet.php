<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

use Box\Spout\Common\Entity\Cell;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\CSV\Reader as CSVReader;
use Box\Spout\Reader\ReaderInterface;
use Box\Spout\Reader\SheetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a source plugin that migrates from spreadsheet files.
 *
 * Largely drop-in replacement for migrate_spreadsheet's source; with many of
 * the same available configuration keys:
 * - file: The path to the source file. The path can be either relative to
 *   Drupal root but it can be a also an absolute reference such as a stream
 *   wrapper; however, a .ods or .xlsx _must_ be able to be realpath'd to a
 *   real location, at present.
 * - worksheet: The name of the worksheet to read from.
 * - header_row: The row where the header is placed. If the table header is on
 *   the first row, this configuration should be 1. The header cell values will
 *   act as column names. The value of 2 means that the table header is on the
 *   second row.
 * - columns: The list of columns to be returned. Is basically a list of table
 *   header cell values, if a header has been defined with `header_row`. If
 *   there's no table header (i.e. `header_row` is missing), it should contain a
 *   list/sequence of column letters (A, B, C, ...). If this configuration is
 *   missed, all columns that contain data will be be returned (not
 *   recommended).
 * - row_index_column: The name to be given to the column containing the row
 *   index. If this setting is specified, the source will return also a pseudo-
 *   column, with this name, containing the row index. The value here can/should
 *   be used later in `keys` list to make this column a primary key column. This
 *   name doesn't need to be appended to the `columns` list, it will be added
 *   automatically.
 * - keys: The primary key as a list of keys. It's a list of source columns that
 *   are composing the primary key. The list is keyed by column name and has the
 *   field storage definition as value. If the table have a header (i.e.
 *   `header_row` is set) the keys will be set as the name of header cells
 *   acting as primary index. Otherwise the column letters (A, B, C, ...) can be
 *   used. If no keys are defined here, the current row position will be
 *   returned as primary key, but in this case, `row_index_column` must have a
 *   value.
 *
 * NOTE: The one unimplemented value, which is `origin`... instead, we expect
 * that the data proper starts on the row following the header (or on the very
 * first line, if there's no applicable `header_row` (and `columns` is being
 * used to provide the mapping.
 *
 * @MigrateSource(
 *   id = "isi_spreadsheet",
 * )
 */
class Spreadsheet extends SourcePluginBase implements ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * The reader for the spreadsheet when open.
   *
   * @var \Box\Spout\Reader\ReaderInterface
   */
  protected ?ReaderInterface $reader = NULL;

  /**
   * Memoized column names in the spreadsheet.
   *
   * @var string[]
   */
  protected ?array $columns = NULL;

  /**
   * Filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('file_system')
    );
  }

  /**
   * Destructor.
   */
  public function __destruct() {
    $this->closeReader();
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    // XXX: "rewind()" by recreating the underlying iterator, since we make
    // use of the generator business.
    unset($this->iterator);
    $this->next();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'file' => NULL,
      'worksheet' => NULL,
      'header_row' => 0,
      'columns' => [],
      'keys' => [],
      'row_index_column' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $this->configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return $this->configuration['file'] . ':' . $this->configuration['worksheet'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $config = $this->getConfiguration();

    if (empty($config['keys'])) {
      if (empty($config['row_index_column'])) {
        throw new \RuntimeException("Row index should act as key but no name has been provided. Set 'row_index_column' in source config to provide a name for this column.");
      }
      // If no keys are defined, we'll use the 'zero based' index of the
      // spreadsheet current row.
      return [$config['row_index_column'] => ['type' => 'integer']];
    }

    return $config['keys'];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    if ($this->columns === NULL) {
      // No column headers provided in config, read worksheet for header row.
      if (!$columns = $this->getConfiguration()['columns']) {
        $columns = $this->getHeaders();
      }
      // Add $row_index_column if it's been configured.
      if ($row_index_column = $this->getConfiguration()['row_index_column']) {
        $columns[] = $row_index_column;
      }
      $this->columns = array_combine($columns, $columns);
    }

    return $this->columns;
  }

  /**
   * Helper; open the spreadsheet reader.
   *
   * @return \Box\Spout\Reader\ReaderInterface
   *   The opened reader.
   */
  protected function openReader() {
    if ($this->reader === NULL) {
      $path = $this->getConfiguration()['file'];
      $realpath = $this->fileSystem->realpath($path);
      $reader = ReaderEntityFactory::createReaderFromFile($realpath);
      $reader->open($realpath);
      $this->reader = $reader;
    }

    return $this->reader;
  }

  /**
   * Helper; close the spreadsheet reader.
   */
  protected function closeReader() {
    if ($this->reader !== NULL) {
      $this->reader->close();
      unset($this->reader);
    }
  }

  /**
   * Helper; fetch the target worksheet.
   *
   * @return \Box\Spout\Reader\SheetInterface
   *   The target sheet.
   *
   * @throws \OutOfBoundsException
   *   If the sheet could not be found.
   */
  protected function getWorksheet() : SheetInterface {
    $reader = $this->openReader();

    if ($reader instanceof CSVReader) {
      // XXX: CSVs do not have sheet names, so... skip checking.
      foreach ($reader->getSheetIterator() as $sheet) {
        return $sheet;
      }
    }

    $name = $this->getConfiguration()['worksheet'];
    foreach ($this->openReader()->getSheetIterator() as $sheet) {
      if ($sheet->getName() === $name) {
        return $sheet;
      }
    }

    throw new \OutOfBoundsException("Failed to find worksheet of the name '$name'.");
  }

  /**
   * Helper; get the header row for the given sheet.
   *
   * @return string[]
   *   The array of headers.
   *
   * @throws \RangeException
   *   If the header row could not be found... either trying to do something
   *   with an empty sheet (or not enough rows), or "header_row" being
   *   negative?
   */
  protected function getHeaders() : array {
    foreach ($this->getWorksheet()->getRowIterator() as $index => $row) {
      if ($index === $this->getConfiguration()['header_row']) {
        return $row->toArray();
      }
    }

    throw new \RangeException("Failed to find header row.");
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator(): \Traversable {
    $row_index_column = $this->getConfiguration()['row_index_column'];
    $field_count_less_index = count($this->fields()) - ($row_index_column ? 1 : 0);

    foreach ($this->getWorksheet()->getRowIterator() as $index => $row) {
      if ($index <= $this->getConfiguration()['header_row']) {
        continue;
      }

      $cells = $row->toArray();

      $cell_count = count($cells);
      if ($cell_count > $field_count_less_index) {
        $cells = array_slice($cells, 0, $field_count_less_index);
      }
      elseif ($cell_count < $field_count_less_index) {
        $cells = array_pad($cells, $field_count_less_index, '');
      }

      if ($row_index_column) {
        $cells[] = $index;
      }

      yield array_combine($this->fields(), $cells);
    }
  }

}
