<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configured source trait for the configured source interface.
 *
 * @see \Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceInterface
 */
trait ConfiguredSourceTrait {

  /**
   * Handle submitted values, specifially.
   *
   * @see \Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceInterface::submitFormValues()
   */
  abstract public function submitFormValues(array $values);

  /**
   * Handle form submission.
   *
   * @see \Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceInterface::submitForm()
   */
  public function submitForm(array $element, FormStateInterface $form_state) {
    $this->submitFormValues($form_state->getValue($element['#parents']));
  }

}
