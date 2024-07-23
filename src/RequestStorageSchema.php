<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Request storage schema service.
 */
class RequestStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritDoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping) {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
    $field_name = $storage_definition->getName();

    if ($table_name === $this->storage->getBaseTable()) {
      if ($field_name === 'machine_name') {
        $this->addSharedTableFieldUniqueKey($storage_definition, $schema);
      }
      elseif ($field_name === 'active') {
        $this->addSharedTableFieldIndex($storage_definition, $schema);
      }
    }

    return $schema;
  }

}
