<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for isi_request entities.
 */
class RequestAccessControlHandler extends EntityAccessControlHandler {

  const MAP = [
    'update' => ['edit islandora_spreadsheet_ingest requests'],
    'map' => ['edit islandora_spreadsheet_ingest request mapping'],
    'view' => ['view islandora_spreadsheet_ingest requests'],
    'activate' => ['activate islandora_spreadsheet_ingest requests'],
    'delete' => ['delete islandora_spreadsheet_ingest requests'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return parent::checkCreateAccess($account, $context, $entity_bundle)
      ->orIf(AccessResult::allowedIfHasPermission($account, 'create islandora_spreadsheet_ingest requests'));
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return parent::checkAccess($entity, $operation, $account)
      ->orIf($this->specificCheckAccess($entity, $operation, $account));
  }

  /**
   * Helper; check our specific requirements.
   *
   * @see ::checkAccess()
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Our result.
   */
  protected function specificCheckAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!isset(static::MAP[$operation])) {
      return AccessResult::neutral();
    }
    else {
      return AccessResult::allowedIfHasPermissions($account, static::MAP[$operation])
        ->andIf(
          AccessResult::allowedIf($entity->getOwner() == $account->id())
            ->cachePerUser()
        );
    }
  }

}
