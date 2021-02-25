<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\Core\Form\FormStateInterface;

/**
 * Extended source to permit some configuration.
 */
interface ConfiguredSourceInterface extends SourceInterface {

  /**
   * Get the source configuration form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function getForm(FormStateInterface $form_state);

  /**
   * Handle form submission.
   *
   * @param array $element
   *   The element being submitted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state being submitted.
   */
  public function submitForm(array $element, FormStateInterface $form_state);

  /**
   * Actually deal with storing values on the given object.
   *
   * @param array $values
   *   The values to store.
   */
  public function submitFormValues(array $values);

}
