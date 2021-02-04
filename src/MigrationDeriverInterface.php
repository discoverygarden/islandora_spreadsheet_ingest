<?php

namespace Drupal\islandora_spreadsheet_ingest;

interface MigrationDeriverInterface {
  public function createAll(RequestInterface $request);
  public function deleteAll(RequestInterface $request);
}
