<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

use Drupal\islandora_spreadsheet_ingest\Util\MigrationRollbackBatch;
use Drupal\migrate_tools\MigrateExecutable;
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
   * The queue in which to create items for deferred executions.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $deferredQueue;

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
    $instance->deferredQueue = $container->get('islandora_spreadsheet_ingest.deferred_ingest.queue');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['enqueue'] = [
      '#type' => 'radios',
      '#title' => $this->t('Processing'),
      '#options' => array_column($this->processingMethods(), 'label'),
      '#default_value' => '',
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
    // XXX: Handle a scenario where a migration entity was somehow created
    // where active wasn't set to TRUE initially.
    if (!$this->entity->getActive()) {
      $this->entity
        ->set('active', TRUE)
        ->save();
      // XXX: Clear the plugin manager's cache in case new things were derived
      // in an entity hook (example: request being activated).
      $this->migrationPluginManager->clearCachedDefinitions();
    }
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
  public function runBatchOp(MigrationInterface $migration, MigrateExecutable $e, &$context) {
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
   * Helper; get map of methods and callbacks.
   *
   * @return array
   *   An associative array mapping machine names to associative arrays
   *   containing:
   *   - label: A label for the given method.
   *   - callable: The callback to use to process the method.
   */
  protected function processingMethods() {
    return [
      'manual' => [
        'label' => $this->t('Manual'),
        'callable' => [$this, 'submitProcessManual'],
      ],
      'deferred' => [
        'label' => $this->t('Deferred'),
        'callable' => [$this, 'submitProcessDeferred'],
      ],
      'immediate' => [
        'label' => $this->t('Immediate'),
        'callable' => [$this, 'submitProcessImmediate'],
      ],
      'rollback_migration_group' => [
        'label' => $this->t('Rollback Migration Group'),
        'callable' => [$this, 'submitProcessRollbackMigrationGroup'],
      ],
    ];
  }

  /**
   * Callback for the "manual" method.
   */
  protected function submitProcessManual(array &$form, FormStateInterface $form_state) {
    $this->messenger->addMessage($this->t('Execute via the <a href=":migration_group">migration group</a> or some other mechanism.', [
      ':migration_group' => Url::fromRoute('entity.migration.list', [
        'migration_group' => $this->migrationGroupDeriver->deriveName($this->entity),
      ])->toString(),
    ]));
  }

  /**
   * Callback for the "deferred" method.
   */
  protected function submitProcessDeferred(array &$form, FormStateInterface $form_state) {
    $this->deferredQueue->createItem($this->entity->id());
    $this->messenger->addMessage($this->t('Enqueued.'));
  }

  /**
   * Callback for the "immediate" method.
   */
  protected function submitProcessImmediate(array &$form, FormStateInterface $form_state) {
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

  /**
   * Callback for the "rollback_migration_group" method.
   */
  protected function submitProcessRollbackMigrationGroup(): void {
    try {
      $migrations = $this->migrationPluginManager->createInstancesByTag($this->migrationGroupDeriver->deriveTag($this->entity));
      $batch = [
        'operations' => [],
      ];

      foreach ($migrations as $migration) {
        $executable = new MigrationRollbackBatch($migration, $this->messenger, [
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
    } catch (\Exception $e) {
      $this->logger('isi.review')->error("Failed to roll back migration: {exc}\n{backtrace}", [
        'exc' => $e->getMessage(),
        'backtrace' => $e->getTraceAsString(),
      ]);
      $this->messenger->addError($this->t("Failed to rollback migration."));
    }
  }

  /**
   * Finish callback for the rollback batch process.
   */
  private function finishRollbackBatchCallback($success, $results, $operations): void {
    if ($success) {
      // Assuming $results['processed'] contains the list of processed migrations
      foreach ($results['processed'] as $migration_id) {
        $this->messenger->addMessage(
          $this->t('Migration @migration_id has been rolled back.', ['@migration_id' => $migration_id])
        );
      }
      \Drupal::logger('isi.review')->info('All migrations have been rolled back successfully.');
    } else {
      $this->messenger->addError($this->t('One or more migrations failed to roll back.'));
      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $this->logger('isi.review')->error('Migration rollback failed with exception: {exc}', ['exc' => $error]);
        }
      }
    }
  }


  /**
   * Submission handler; route to selected processing method.
   */
  public function submitProcessing(array &$form, FormStateInterface $form_state) {
    if ($this->entity->getActive()) {
      $mode = $form_state->getValue('enqueue');

      call_user_func_array(array_column($this->processingMethods(), 'callable')[$mode], [
        &$form,
        $form_state,
      ]);
    }
  }

}
