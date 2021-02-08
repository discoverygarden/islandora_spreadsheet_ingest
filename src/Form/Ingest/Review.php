<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Component\Graph;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\migrate\MigrateMessage;
use Drupal\dgi_migrate\MigrateBatchExecutable;
//use Drupal\migrate_tools\MigrateBatchExecutable;

/**
 * Form for setting up ingests.
 */
class Review extends EntityForm {

  protected $entityTypeManager;
  protected $migrationStorage;
  protected $migrationGroupDeriver;
  protected $migrationPluginManager;
  protected $messenger;

  public static function create(ContainerInterface $container) {
    $instance = new static();

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->migrationStorage = $instance->entityTypeManager->getStorage('migration');
    $instance->migrationGroupDeriver = $container->get('islandora_spreadsheet_ingest.migration_group_deriver');
    $instance->migrationPluginManager = $container->get('plugin.manager.migration');
    $instance->messenger = $container->get('messenger');

    return $instance;
  }

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
          ':input[name="active"]' => [
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
        // Setup batch(es) to process the group.
        $migrations = $this->migrationPluginManager->createInstancesByTag($this->migrationGroupDeriver->deriveTag($this->entity));

        try {
          $messenger = new MigrateMessage();
          foreach ($migrations as $migration) {
            $executable = new MigrateBatchExecutable($migration, $messenger, [
              'limit' => 0,
              'update' => 0,
              'force' => 0,
            ]);
            batch_set($executable->prepareBatch());
          }
        }
        catch (\Exception $e) {
          $this->logger('isi.review')->error('Failed to enqueue batch: {exc}', ['exc' => $e->getMessage()]);
          $this->messenger->addError($this->t('Failed to enqueue batch.'));
        }
      }
    }
  }

}
