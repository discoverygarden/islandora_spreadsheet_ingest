<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface RequestInterface extends ConfigEntityInterface {
  /**
   * @return Drupal\islandora_spreadsheet_ingest\SheetInterface
   */
  public function getSheet();

  public function getMappings();

  public function getActive();
}
