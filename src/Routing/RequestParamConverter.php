<?php

namespace Drupal\islandora_spreadsheet_ingest\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Allow for access of requests by machine name.
 */
class RequestParamConverter implements ParamConverterInterface, ContainerInjectionInterface {

  use DependencySerializationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->requestStorage = $this->entityTypeManager->getStorage('isi_request');
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $request_storage = $this->entityTypeManager->getStorage('isi_request');
    if (is_numeric($value) && ($request = $request_storage->load($value))) {
      return $request;
    }
    if ($requests = $request_storage->loadByProperties(['machine_name' => $value])) {
      return reset($requests);
    }

    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function applies($definition, $name, Route $route) {
    return isset($definition['type']) && $definition['type'] === 'islandora_spreadsheet_ingest_request';
  }

}
