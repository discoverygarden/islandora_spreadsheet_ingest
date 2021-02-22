<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePlugin;

use Drupal\Core\Form\FormStateInterface;

use Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceInterface;
use Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceTrait;
use Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginBase;

/**
 * Default value plugin.
 *
 * @PipelineSourcePlugin(
 *   id = "default_value",
 *   label = @Translation("Default Value"),
 * )
 */
class DefaultValue extends PipelineSourcePluginBase implements ConfiguredSourceInterface {

  use ConfiguredSourceTrait;

  /**
   * {@inheritdoc}
   */
  public function getSourceName() {
    return $this->t('System');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->configuration['default_value'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value) {
    $this->configuration['default_value'] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return ($this->getValue() !== NULL) ?
      $this->t('Default value: ":value"', [':value' => $this->getValue()]) :
      $this->t('New default value');
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(FormStateInterface $form_state) {
    return [
      'default_value' => [
        '#type' => 'textarea',
        '#title' => $this->t('Default value'),
        '#description' => $this->t('The default value to assign, if none supercedes.'),
        '#default_value' => $this->getValue(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormValues(array $values) {
    $this->setValue($values['default_value']);
  }

  /**
   * {@inheritdoc}
   */
  public function toProcessArray() {
    if (!$this->getValue()) {
      throw new \Exception('Missing default value; nothing to bind!');
    }

    return [
      'plugin' => $this->pluginId,
      'default_value' => $this->getValue(),
    ];
  }

}
