<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\dgi_migrate\MigrateBatchExecutable;

/**
 * Form for setting up ingests.
 */
class Review extends EntityForm {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Migration storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $migrationStorage;

  /**
   * Migration group deriver.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface
   */
  protected $migrationGroupDeriver;

  /**
   * Migration plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
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
   * Run a batch migration import operation.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration being run.
   * @param \Drupal\dgi_migrate\MigrateBatchExecutable $e
   *   A migration batch exectuable to manipulate, to run the batch.
   * @param mixed $context
   *   A reference to the batch context.
   */
  public function runBatchOp(MigrationInterface $migration, MigrateBatchExecutable $e, &$context) {
    $sandbox =& $context['sandbox'];

    if (!isset($sandbox['prepped'])) {
      $context['results']['status'] = NULL;

      try {
        $e->prepareBatch();
      }
      catch (\Exception $ex) {
        $e->finishBatch(FALSE, [], [], NULL);
      }
      $sandbox['prepped'] = TRUE;
    }

    $e->processBatch($context);

    if ($context['results']['status'] === MigrationInterface::RESULT_COMPLETED || $sandbox['total'] === 0 || $context['finished'] == 1) {
      $e->finishBatch(TRUE, $context['results'], [], NULL);
    }
    elseif ($context['results']['status'] === MigrationInterface::RESULT_FAILED) {
      $e->finishBatch(FALSE, $context['results'], [], NULL);
    }
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
          $batch = [
            'operations' => [],
          ];

          foreach ($migrations as $migration) {
            $executable = new MigrateBatchExecutable($migration, $messenger, [
              'limit' => 0,
              'update' => 0,
              'force' => 0,
            ]);
            $batch['operations'][] = [
              [$this, 'runBatchOp'],
              [$migration, $executable],
            ];
          }
          batch_set($batch);
        }
        catch (\Exception $e) {
          $this->logger('isi.review')->error("Failed to enqueue batch: {exc}\n{backtrace}", [
            'exc' => $e->getMessage(),
            'backtrace' => $e->getTraceAsString(),
          ]);
          $this->messenger->addError($this->t('Failed to enqueue batch.'));
        }
      }
    }
  }

}
