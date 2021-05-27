<?php

namespace Drupal\islandora_spreadsheet_ingest\Controller;

use Drupal\Core\Link;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Request config entity list builder.
 */
class RequestListBuilder extends ConfigEntityListBuilder {

  /**
   * The migration group deriver service.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface
   */
  protected $migrationGroupDeriver;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];

    $header['label'] = $this->t('Request');
    $header['id'] = $this->t('ID');
    $header['active'] = $this->t('Active');
    $header['migration_group'] = $this->t('Migration group');

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

    $mg_name = $this->migrationGroupDeriver->deriveName($entity);
    // XXX: The migration_group entity does not actually have this listed as one
    // of its routes (it is instead on the "migration" entity for some reason...
    // ... anyway... let's build out a link to it.
    $mg_link = Link::createFromRoute(
      $mg_name,
      'entity.migration.list',
      ['migration_group' => $mg_name]
    );
    $activate_link = $entity->toLink($entity->getActive() ? $this->t('Active') : $this->t('Inactive'), 'activate-form');
    $row['active'] = $activate_link->getUrl()->access() ?
      $activate_link :
      $activate_link->getText();
    $row['migration_group'] = $entity->getActive() ?
       ($mg_link->getUrl()->access() ?
        $mg_link :
        $mg_link->getText()) :
       '';

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

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);

    $instance->migrationGroupDeriver = $container->get('islandora_spreadsheet_ingest.migration_group_deriver');

    return $instance;
  }

}
