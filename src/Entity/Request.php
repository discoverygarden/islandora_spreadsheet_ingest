<?php

namespace Drupal\islandora_spreadsheet_ingest\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;

use Drupal\islandora_spreadsheet_ingest\RequestInterface;
use Drupal\islandora_spreadsheet_ingest\SheetInterface;

/**
 * Defines the Request entity.
 *
 * @ConfigEntityType(
 *   id = "isi_request",
 *   label = @Translation("Islandora Spreadsheet Ingest Request"),
 *   handlers = {
 *     "list_builder" = "Drupal\islandora_spreadsheet_ingest\Controller\RequestListBuilder",
 *     "form" = {
 *       "add" = "Drupal\islandora_spreadsheet_ingest\Form\Ingest\FileUpload",
 *       "edit" = "Drupal\islandora_spreadsheet_ingest\Form\Ingest\Mapping",
 *     }
 *   },
 *   config_prefix = "request",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "sheet",
 *     "mappings",
 *   }
 * )
 */
class Request extends ConfigEntityBase implements RequestInterface {
  protected $storage;

  protected $id;
  protected $label;
  protected $sheet;
  protected $originalMapping = 'migration_group:isi';
  protected $mappings = NULL;

  public function getOriginalMapping() {
    return $this->originalMapping;
  }

  public function getSheet() {
    return $this->sheet;
  }

  public function getMappings() {
    return $this->mappings;
  }

}
