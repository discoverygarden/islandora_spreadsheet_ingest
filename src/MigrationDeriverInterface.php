<?php

namespace Drupal\islandora_spreadsheet_ingest;

/**
 * Migration deriver interface.
 */
interface MigrationDeriverInterface {

  /**
   * Create all the migrations for the given request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $reqeust
   *   The request for which to create the migration entities.
   */
  public function createAll(RequestInterface $request);

  /**
   * Delete all the migrations for the given request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $reqeust
   *   The request for which to delete the migration entities.
   */
  public function deleteAll(RequestInterface $request);

  /**
   * Identify the columns from the source spreadsheet which are used here.
   *
   * @param array $mappings
   *   The array of mappings to scan.
   */
  public function getUsedColumns(array $mappings);

}
