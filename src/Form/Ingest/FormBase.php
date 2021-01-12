<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Form\FormBase as DrupalFormBase;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

abstract class FormBase extends DrupalFormBase {
  const MG = 'islandora_spreadsheet_example';
  const TEMP_STORE_NAME = 'islandora_spreadsheet_ingest.csv_ingest';

  protected $store;

  protected function __construct(PrivateTempStoreFactory $private_temp_store_factory) {
    $this->store = $private_temp_store_factory->get(static::TEMP_STORE_NAME);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#submit' => ['::submitCancel'],
        '#weight' => 100,
      ],
    ];

    return $form;
  }

  public function submitCancel(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('<front>');
  }
}
