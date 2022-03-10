<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin;

use Drupal\migrate\Plugin\Migration;

class WeakSourceMigration extends Migration {

  protected ?\WeakReference $weakSource = NULL;

  /**
   * {@inheritdoc}
   */
  public function getSourcePlugin() {
    \Drupal::logger('asdf')->debug('doing the weak source thing');
    if ($this->weakSource === NULL || ($source = $this->weakSource->get()) === NULL) {
      $source = $this->sourcePluginManager->createInstance($this->source['plugin'], $this->source, $this);
      $this->weakSource = \WeakReference::create($source);
    }
    return $source;
  }


}
