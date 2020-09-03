<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Removes the overidden file_system service decorated by flysystem_s3.
 */
class IslandoraSpreadsheetIngestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // XXX: There is currently an issue with decoratored services blowing up on
    // serialization. This is a noted issue on d.o but there hasn't been any
    // traction among solving it. There was an issue to fix a bug with private
    // buckets that introduced a decorator that has a myriad of issues. Whether
    // or not that issue is going to be re-written/reverted is still tbd. For
    // now we are going to remove their decoration outright.
    //
    // @see: https://www.drupal.org/project/drupal/issues/2896993
    // @see: https://www.drupal.org/project/flysystem_s3/issues/3133318
    $container->removeDefinition('flysystem_s3.file_system');
  }

}
