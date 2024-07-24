<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Module settings form.
 */
class Admin extends ConfigFormBase {

  /**
   * Drupal's stream wrapper manager service..
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * Drupal's module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return parent::create($container)
      ->setStreamWrapperManager($container->get('stream_wrapper_manager'))
      ->setModuleHandler($container->get('module_handler'));
  }

  /**
   * Setter for the stream wrapper manager service.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager service to set.
   *
   * @return $this
   *   Fluent interface.
   */
  public function setStreamWrapperManager(StreamWrapperManagerInterface $streamWrapperManager) : static {
    $this->streamWrapperManager = $streamWrapperManager;
    return $this;
  }

  /**
   * Setter for the module handler service.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service to set.
   *
   * @return $this
   *   Fluent interface.
   */
  public function setModuleHandler(ModuleHandlerInterface $moduleHandler) : static {
    $this->moduleHandler = $moduleHandler;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('islandora_spreadsheet_ingest.settings');
    $current_whitelist = $config->get('binary_directory_whitelist');
    $form['schemes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Schemes'),
      '#description' => $this->t('Allowed list of schemes for which binaries can be referenced from.'),
      '#default_value' => $config->get('schemes') ?? [],
      '#options' => $this->streamWrapperManager->getNames(StreamWrapperInterface::READ_VISIBLE),
    ];
    $form['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Binary path whitelist'),
      '#default_value' => $current_whitelist ? implode(',', $current_whitelist) : '',
      '#description' => $this->t('A comma separated list of local locations from which spreadsheet ingests can use binaries.'),
    ];
    $form['config_ignore_integration'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable config_ignore integration?'),
      '#default_value' => $config->get('enable_config_ignore_integration'),
      '#description' => $this->t('This module results in many "migrate_plus" config entities being created; however, these config entities should typically be synchronized between systems. Therefore, we integrate with "config_ignore" to ignore the given entities.'),
      '#disabled' => !$this->moduleHandler->moduleExists('config_ignore'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_spreadsheet_ingest.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_spreadsheet_ingest.settings');
    $whitelist = array_filter(explode(',', $form_state->getValue('paths')));
    $config->set('binary_directory_whitelist', $whitelist);
    $config->set('schemes', array_filter($form_state->getValue('schemes')));
    $config->set('enable_config_ignore_integration', $form_state->getValue('config_ignore_integration'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
