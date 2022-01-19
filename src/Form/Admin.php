<?php

namespace Drupal\islandora_spreadsheet_ingest\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
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
   * The StreamWrapperManager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, StreamWrapperManagerInterface $stream_wrapper_manager) {
    parent::__construct($config_factory);
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('stream_wrapper_manager')
    );
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
    $form = [];
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
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
