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
   * Drupal's stream wrapper manager service.
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
    $scheme_options = $this->streamWrapperManager->getNames(StreamWrapperInterface::READ_VISIBLE) + [
      'http' => 'HTTP',
      'https' => 'HTTPS',
      'ftp' => 'FTP',
    ];
    $form['schemes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Schemes'),
      '#description' => $this->t('Allowed list of schemes for which binaries can be referenced from.'),
      '#default_value' => $config->get('schemes') ?? [],
      '#options' => $scheme_options,
    ];
    $form['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Binary path whitelist'),
      '#default_value' => $current_whitelist ? implode(',', $current_whitelist) : '',
      '#description' => $this->t('A comma separated list of local locations from which spreadsheet ingests can use binaries.'),
    ];
    $form['notifications'] = [
      '#type' => 'details',
      '#title' => $this->t('Failure notifications'),
      '#open' => (bool) $config->get('notifications.enabled'),
      '#tree' => TRUE,
    ];
    $form['notifications']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email when a deferred ingest has failures'),
      '#description' => $this->t('Sent when the ingest process fails or any rows failed to import.'),
      '#default_value' => $config->get('notifications.enabled') ?? FALSE,
    ];
    $form['notifications']['notify_owner'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify the owner of the request'),
      '#default_value' => $config->get('notifications.notify_owner') ?? TRUE,
    ];
    $form['notifications']['recipients'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional recipients'),
      '#description' => $this->t('Email addresses, one per line.'),
      '#default_value' => implode("\n", $config->get('notifications.recipients') ?? []),
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
    $notifications = $form_state->getValue('notifications');
    $config->set('notifications', [
      'enabled' => (bool) $notifications['enabled'],
      'notify_owner' => (bool) $notifications['notify_owner'],
      'recipients' => array_values(array_filter(array_map('trim', explode("\n", $notifications['recipients'])))),
    ]);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
