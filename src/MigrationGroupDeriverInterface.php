<?php

namespace Drupal\islandora_spreadsheet_ingest;

interface MigrationGroupDeriverInterface {
  public function create(RequestInterface $request);
  public function delete(RequestInterface $request);
  public function deriveName(RequestInterface $request);
}
