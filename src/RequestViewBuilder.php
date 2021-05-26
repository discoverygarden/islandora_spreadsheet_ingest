<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Render\Element;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View builder for isi_request entities.
 */
class RequestViewBuilder extends EntityViewBuilder {

  /**
   * User/account storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * The migration group deriver service.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface
   */
  protected $migrationGroupDeriver;

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $build_list) {

    foreach (Element::children($build_list) as $key) {
      $item =& $build_list[$key];

      $item['label'] = [
        '#type' => 'item',
        '#title' => $this->t('Label'),
        '#markup' => $item['#isi_request']->label(),
      ];

      $owner = $this->userStorage->load($item['#isi_request']->getOwner());
      $item['owner'] = [
        '#type' => 'item',
        '#title' => $this->t('Owner'),
        'username' => ($owner ?
          [
            '#theme' => 'username',
            '#account' => $owner,
          ] :
          $this->t('Unknown user')),
      ];
      $item['active'] = [
        '#type' => 'item',
        '#title' => $this->t('Active'),
        '#markup' => ($item['#isi_request']->getActive() ?
          $this->t('Yes') :
          $this->t('No')),
      ];
      $mg_name = $this->migrationGroupDeriver->deriveName($entity);
      $mg_link = Link::createFromRoute(
        $mg_name,
        'entity.migration.list',
        ['migration_group' => $mg_name]
      );
      $item['migration_group'] = [
        '#type' => 'item',
        '#access' => $item['#isi_request']->getActive(),
        '#markup' => ($mg_link->getUrl()->access() ?
          $mg_link :
          $mg_link->getText()),
      ];
    }

    return $build_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);

    $instance->userStorage = $container->get('entity_type.manager')->getStorage('user');
    $instance->migrationGroupDeriver = $container->get('islandora_spreadsheet_ingest.migration_group_deriver');

    return $instance;
  }

}
