<?php

namespace Drupal\islandora_spreadsheet_ingest\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

use Drupal\islandora_spreadsheet_ingest\Model\ConfiguredSourceInterface;

/**
 * Migration mappings wrapper element.
 *
 * @FormElement("islandora_spreadsheet_ingest_migration_mapping_source")
 */
class MappingSource extends FormElement {

  const NOTHING = '-\\_/- select -\\_/-';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#tree' => TRUE,
      '#input' => FALSE,
      '#properties' => [],
      '#process' => [
        [static::class, 'processProperties'],
      ],
      'select' => [
        '#type' => 'select',
        '#title' => $this->t('Value Source'),
        '#empty_value' => static::NOTHING,
        '#options' => [],
      ],
      'config' => [
        '#type' => 'container',
      ],
      '#element_validate' => [
        [static::class, 'validateSelectedForm'],
      ],
    ];
  }

  /**
   * Helper; get the machine name for the current selection.
   */
  protected static function getSelected(array $element, $selection) {
    if ($selection === static::NOTHING) {
      throw new \Exception('Missing selection.');
    }
    elseif (!isset($element['#properties'][$selection])) {
      throw new \Exception('Selection does not exist.');
    }

    return $element['#properties'][$selection];

  }

  /**
   * Helper; given a selection, get the related property/field mapping.
   */
  protected static function getSelectedProperty(array $element, FormStateInterface $form_state) {
    $selection = $form_state->getValue(array_merge($element['#parents'], ['select']), static::NOTHING);

    return [$selection, static::getSelected($element, $selection)];
  }

  /**
   * Element validation; check that the selected source is valid, if adding.
   */
  public static function validateSelectedForm(array &$element, FormStateInterface $form_state, array $form) {
    if (array_slice($element['#parents'], 0, -1) !== array_slice($form_state->getTriggeringElement()['#parents'], 0, -1)) {
      // XXX: To avoid polluting having to push out "#limit_validation_error"
      // stuff to other parts of the form... just check things here.
      return;
    }
    try {
      list(, $prop) = static::getSelectedProperty($element, $form_state);
      $form_state->setValueForElement($element, $prop);
    }
    catch (\Exception $e) {
      $form_state->setError($element, $e->getMessage());
      return;
    }
  }

  /**
   * Process callback; throw in any forms for configured sources.
   */
  public static function processProperties(array &$element, FormStateInterface $form_state) {
    // Generate the #name, as per:
    // https://git.drupalcode.org/project/drupal/-/blob/1b29cf27aa94996407170fa1f77760745e4f3520/core/lib/Drupal/Core/Form/FormBuilder.php#L1167-1185
    $parents = array_merge($element['#parents'], ['select']);
    $select_target = implode('', [
      array_shift($parents),
      '[',
      implode('][', $parents),
      ']',
    ]);

    foreach ($element['#properties'] as $name => $prop) {
      $element['select']['#options']["{$prop->getSourceName()}"][$name] = $prop->getName();

      if ($prop instanceof ConfiguredSourceInterface) {
        $element['config'][$name] = $prop->getForm($form_state) + [
          '#type' => 'container',
          '#states' => [
            'visible' => [
              ":input[name=\"{$select_target}\"]" => [
                'value' => $name,
              ],
            ],
          ],
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      // Get the property for the thing that was selected, do its form
      // handling, and return it.
      try {
        list($name, $prop) = static::getSelectedProperty($element, $form_state);
        if ($prop instanceof ConfiguredSourceInterface) {
          $prop->submitFormValues($form_state->getValue(array_merge(
            $element['#parents'],
            ['config', $name]
          )));
        }
        $form_state->setValueForElement($element, $prop);
        return $prop;
      }
      catch (\Exception $e) {
        // No-op.
      }
    }
  }

}
