<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\Core\Form\FormStateInterface;

interface ConfiguredSourceInterface extends SourceInterface {
  public function getForm(FormStateInterface $form_state);
  public function submitForm($element, FormStateInterface $form_state);
}
