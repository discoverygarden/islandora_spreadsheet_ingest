<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for setting up ingests.
 */
class Review extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Activate'),
      '#description' => $this->t('Activate, and derive processable migrations for this request.'),
      '#default_value' => $this->entity->getActive(),
    ];
    $form['enqueue'] = [
      '#type' => 'radios',
      '#title' => $this->t('Processing'),
      '#options' => [
        'defer' => $this->t('Deferred'),
        'immediate' => $this->t('Immediate'),
      ],
      '#default_value' => 'defer',
      '#states' => [
        'visible' => [
          ':input[name="active"' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    unset($actions['submit']);

    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#submit' => [
        '::submitActivation',
        '::submitProcessing',
      ],
    ];

    return $actions;
  }

  /**
   * Submission handler; submit the "active" value.
   */
  public function submitActivation(array &$form, FormStateInterface $form_state) {
    $this->entity
      ->set('active', $form_state->getValue('active'))
      ->save();
  }

  /**
   * Submission handler; kick off batch if relevant.
   */
  public function submitProcessing(array &$form, FormStateInterface $form_state) {
    if ($this->entity->getActive()) {
      if ($form_state->getValue('enqueue') == 'immediate') {
        // @todo Setup a batch to process the group.
        dsm('TODO: Actually setup the batch to batch...');
      }
    }
  }

}
