<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\Core\Form\FormStateInterface;

trait ConfiguredSourceTrait {
  abstract public function submitFormValues(array $values);

  public function submitForm(array $element, FormStateInterface $form_state) {
    $this->submitFormValues($form_state->getValue($element['#parents']));
  }
}
