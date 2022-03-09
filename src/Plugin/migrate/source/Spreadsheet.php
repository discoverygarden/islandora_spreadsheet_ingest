<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_spreadsheet\Plugin\migrate\source\Spreadsheet as UpstreamSpreadsheet;
use Drupal\migrate_spreadsheet\SpreadsheetIterator;
use Drupal\migrate_spreadsheet\SpreadsheetIteratorInterface;


/**
 * Provides a source plugin that migrate from spreadsheet files.
 *
 * Adapted from upstream to avoid maintaining initialized iterators around.
 *
 * @MigrateSource(
 *   id = "isi_spreadsheet",
 * )
 */
class Spreadsheet extends UpstreamSpreadsheet {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('file_system'),
      NULL
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    // No column headers provided in config, read worksheet for header row.
    if (!$columns = $this->getConfiguration()['columns']) {
      $columns = array_keys($this->initializeIterator()->getHeaders());
    }
    // Add $row_index_column if it's been configured.
    if ($row_index_column = $this->getConfiguration()['row_index_column']) {
      $columns[] = $row_index_column;
    }
    return array_combine($columns, $columns);
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator(): SpreadsheetIteratorInterface {
    $configuration = $this->getConfiguration();
    $configuration['worksheet'] = $this->loadWorksheet();
    $configuration['keys'] = array_keys($configuration['keys']);

    // The 'file' and 'plugin' items are not part of iterator configuration.
    unset($configuration['file'], $configuration['plugin']);

    $iterator = new SpreadsheetIterator();
    $iterator->setConfiguration($configuration);
    return $iterator;
  }

}
