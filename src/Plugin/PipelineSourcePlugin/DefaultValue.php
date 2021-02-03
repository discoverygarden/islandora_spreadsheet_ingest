<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePlugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceInterface;
use Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceTrait;
use Drupal\islandora_spreadsheet_ingest\Plugin\PipelineSourcePluginInterface;

/**
 * Default value plugin.
 *
 * @PipelineSourcePlugin(
 *   id = "default_value",
 *   label = @Translation("Default Value"),
 * )
 */
class DefaultValue extends PluginBase implements PipelineSourcePluginInterface, ConfiguredSourceInterface {

  use ConfiguredSourceTrait;

  public function getSourceName() {
    return t('System');
  }

  public function getValue() {
    return $this->configuration['default_value']) ?? NULL;
  }

  public function setValue($value) {
    $this->configuration['default_value'] = $value;
    return $this;
  }

  public function getName() {
    return ($this->getValue() !== NULL) ?
      t('Default value: ":value"', [':value' => $this->getValue()]) :
      t('New default value');
  }

  public function getForm(FormStateInterface $form_state) {
    return [
      'default_value' => [
        '#type' => 'textarea',
        '#title' => t('Default value'),
        '#description' => t('The default value to assign, if none supercedes.'),
        '#default_value' => $this->getValue(),
      ],
    ];
  }

  public function submitFormValues(array $values) {
    $this->setValue($values['default_value']);
  }

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
