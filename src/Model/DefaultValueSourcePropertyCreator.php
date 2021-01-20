<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\Core\Form\FormStateInterface;

class DefaultValueSourcePropertyCreator implements ConfiguredSourceInterface {
  const NAME = 'default_value';

  protected $name;

  public function __construct($name = NULL) {
    $this->name = $name;
  }

  public function getSourceName() {
    return t('System');
  }

  public function getName() {
    return $this->name ? t('Default value: :value', [':value' => $this->name]) : t('New default value');
  }

  public function getForm(FormStateInterface $form_state) {
    return [
      'default_value' => [
        '#type' => 'textarea',
        '#title' => t('Default value'),
        '#description' => t('The default value to assign, if none supercedes.'),
        '#default_value' => $this->name,
      ],
    ];
  }

  public function submitForm($element, FormStateInterface $form_state) {
    $this->name = $form_state->getValue(array_merge(
      $element['#parents'],
      'default_value'
    ));
  }

  public function toProcessArray() {
    if (!$this->name) {
      throw new \Exception('Missing default value; nothing to bind!');
    }

    return [
      'plugin' => static::NAME,
      'default_value' => $this->name,
    ];
  }
}
