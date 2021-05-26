<?php

namespace Drupal\islandora_spreadsheet_ingest\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Request config entity list builder.
 */
class RequestListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];

    $header['label'] = $this->t('Request');
    $header['id'] = $this->t('ID');
    $header['active'] = $this->t('Active');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->toLink(NULL, 'edit-form');
    if (!$row['label']->getUrl()->access()) {
      $row['label'] = $entity->label();
    }
    $row['id'] = $entity->id();
    $row['active'] = $entity->getActive() ? $this->t('Active') : $this->t('Inactive');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $ops = parent::getOperations($entity);

    // Filter to only those operations to which the user has access.
    return array_filter($ops, function ($op) {
      return $op['url']->access();
    });
  }

}
