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
   * Helper; map a cell to its value.
   *
   * @param \Box\Spout\Common\Entity\Cell $cell
   *   The cell of which to get the value.
   *
   * @return mixed
   *   The value of the cell.
   */
  protected static function toValues(Cell $cell) {
    return $cell->getValue();
  }

  /**
   * Helper; fetch the target worksheet.
   *
   * @return \Box\Spout\Reader\SheetInterface
   *   The target sheet.
   *
   * @throws \LogicException
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

    throw new \LogicException("Failed to find worksheet of the name '$name'.");
  }

  /**
   * Helper; get the header row for the given sheet.
   *
   * @return string[]
   *   The array of headers.
   *
   * @throws \LogicException
   *   If the header row could not be found... either trying to do something
   *   with an empty sheet (or not enough rows), or "header_row" being
   *   negative?
   */
  protected function getHeaders() : array {
    foreach ($this->getWorksheet()->getRowIterator() as $index => $row) {
      if ($index === $this->getConfiguration()['header_row']) {
        return array_map([static::class, 'toValues'], $row->getCells());
      }
    }

    throw new \LogicException("Failed to find header row.");
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

      $cells = array_map([static::class, 'toValues'], $row->getCells());

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
