<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Request deletion form.
 */
class RequestDeleteForm extends EntityConfirmFormBase {

  const REDIRECT_ROUTE = 'entity.isi_request.list';

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name? This will not rollback any associated migrations.', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute(static::REDIRECT_ROUTE);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage(
      $this->t('Deleted %label.', [
        '%label' => $this->entity->label(),
      ])
    );
    $form_state->setRedirect(static::REDIRECT_ROUTE);
  }

}
