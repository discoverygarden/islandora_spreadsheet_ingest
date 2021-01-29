<?php

namespace Drupal\islandora_spreadsheet_ingest\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class RequestListBuilder extends ConfigEntityListBuilder {
  public function buildHeader() {
    $header = [];

    $header['label'] = $this->t('Request');
    $header['id'] = $this->t('ID');

    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();

    return $row + parent::buildRow($entity);
  }
}
