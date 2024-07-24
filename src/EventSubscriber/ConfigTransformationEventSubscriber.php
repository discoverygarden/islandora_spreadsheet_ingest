<?php

namespace Drupal\islandora_spreadsheet_ingest\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Config transformation event subscriber.
 *
 * Inspired by https://www.drupal.org/sandbox/ekes/3187856, which deals instead
 * with "webform" entities.
 */
class ConfigTransformationEventSubscriber implements EventSubscriberInterface, ContainerInjectionInterface {

  const MIGRATION_GROUP_PREFIX = 'migrate_plus.migration_group.isi__';
  const MIGRATION_PREFIX = 'migrate_plus.migration.isi__';

  /**
   * Constructor.
   */
  public function __construct(
    protected StorageInterface $activeStorage,
  ) {
    // No-op.
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.storage'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      ConfigEvents::STORAGE_TRANSFORM_EXPORT => 'onExportTransform',
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => 'onImportTransform',
    ];
  }

  /**
   * Config export event handler.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The event to which to respond.
   */
  public function onExportTransform(StorageTransformEvent $event) : void {
    $storage = $event->getStorage();
    foreach ($this->toIgnore($storage) as $name) {
      $storage->delete($name);
    }
  }

  /**
   * Config import event handler.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The event to which to respond.
   */
  public function onImportTransform(StorageTransformEvent $event) : void {
    $storage = $event->getStorage();

    $inbound = iterator_to_array($this->toIgnore($storage), FALSE);
    $current = iterator_to_array($this->toIgnore($this->activeStorage), FALSE);

    // In case a config object escaped let's deal with it.
    foreach (array_diff($inbound, $current) as $to_delete) {
      $storage->delete($to_delete);
    }

    // Keep the current config as the current config.
    foreach ($current as $to_maintain) {
      $storage->write($to_maintain, $this->activeStorage->read($to_maintain));
    }
  }

  /**
   * Helper; yield all the configs that should not change on imports/exports.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The storage from which to enumerate configs.
   *
   * @return \Generator
   *   The names of the configs that should never be changed on imports/exports.
   */
  protected function toIgnore(StorageInterface $storage) {
    yield from $storage->listAll(static::MIGRATION_PREFIX);
    yield from $storage->listAll(static::MIGRATION_GROUP_PREFIX);
  }

}
