<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;

/**
 * Provides the Pipeline Source Plugin plugin manager.
 */
class PipelineSourcePluginManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * Constructs a new PipelineSourcePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/PipelineSourcePlugin', $namespaces, $module_handler, 'Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginInterface', 'Drupal\islandora_spreadsheet_ingest\Annotation\PipelineSourcePlugin');

    $this->alterInfo('islandora_spreadsheet_ingest_isi_pipeline_source_info');
    $this->setCacheBackend($cache_backend, 'islandora_spreadsheet_ingest_isi_pipeline_source_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $config = []) {
    return 'wrapper';
  }

}
