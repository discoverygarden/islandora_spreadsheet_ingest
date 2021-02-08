<?php

namespace Drupal\islandora_spreadsheet_ingest;

/**
 * Migration group deriver interface.
 */
interface MigrationGroupDeriverInterface {

  /**
   * Create the migration group for a given request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The request for which to create a migration group.
   */
  public function create(RequestInterface $request);

  /**
   * Delete the migration group for a given request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The request for which to delete the migration group.
   */
  public function delete(RequestInterface $request);

  /**
   * Derive the name of a migration group for a given request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The request for which to derive a name.
   *
   * @return string
   *   The derived name.
   */
  public function deriveName(RequestInterface $request);

  /**
   * Derive a migration_tags entry for the given request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The request for which to derive a tag.
   *
   * @return string
   *   The derived tag.
   */
  public function deriveTag(RequestInterface $request);

}
