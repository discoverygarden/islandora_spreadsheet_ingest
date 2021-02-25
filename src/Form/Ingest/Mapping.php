<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for setting up ingests.
 */
class Mapping extends EntityForm {

  use MigrationTrait;

  /**
   * File storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * Spreadsheet service.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\Spreadsheet\SpreadsheetServiceInterface
   */
  protected $spreadsheetService;

  /**
   * Migration deriver service.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\MigrationDeriverInterface
   */
  protected $migrationDeriver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();

    $instance->spreadsheetService = $container->get('islandora_spreadsheet_ingest.spreadsheet_service');
    $instance->fileStorage = $container->get('entity_type.manager')->getStorage('file');
    $instance->migrationDeriver = $container->get('islandora_spreadsheet_ingest.migration_deriver');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['#entity_builders'] = [
      '::mapMappings',
    ];

    $form['mappings'] = [
      '#type' => 'islandora_spreadsheet_ingest_migration_mappings',
      '#request' => $this->entity,
      '#input' => FALSE,
    ];

    return $form;
  }

  /**
   * Map and sort our entries.
   */
  protected function mapMappings($entity_type, $entity, &$form, FormStateInterface &$form_state) {
    $map_mapping = function ($info) {
      foreach ($info['entries'] as $key => $process) {
        yield $key => [
          'pipeline' => $process->toPipelineArray(),
        ];
      }
    };

    $map_migrations = function () use ($form_state, $map_mapping) {
      foreach ($form_state->get('migration') as $name => $info) {
        $mappings = iterator_to_array($map_mapping($info));
        $table = $form_state->getValue('mappings')[$name]['table'];
        uksort($mappings, function ($a, $b) use ($table) {
          return $table[$a]['weight'] - $table[$b]['weight'];
        });
        yield $name => [
          'original_migration_id' => $name,
          'mappings' => $mappings,
        ];
      }
    };

    $entity->set('mappings', iterator_to_array($map_migrations()));
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    // Add "save and review" button or whatever.
    $actions['save_and_review'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and proceed'),
      '#validate' => [
        '::preValidateEntity',
        '::validateEntity',
      ],
      '#submit' => array_merge(
        $actions['submit']['#submit'],
        [
          '::redirectToReview',
        ]
      ),
    ];

    return $actions;
  }

  /**
   * Submission handler; redirect to review form.
   */
  public function redirectToReview(array $form, FormStateInterface $form_state) {
    $options = [
      'query' => [],
    ];

    if ($this->getRequest()->query->has('destination')) {
      $options['query'] += $this->getDestinationArray();
      $this->getRequest()->query->remove('destination');
    }

    $form_state->setRedirect(
      'entity.isi_request.activate_form',
      [
        'isi_request' => $this->entity->id(),
      ],
      $options
    );
  }

  /**
   * Build up the base entity to validate.
   *
   * Normally, this would happen later in the submission process.
   */
  public function preValidateEntity(array $form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $entity = $this->buildEntity($form, $form_state);
    $form_state->setTemporaryValue('entity', $entity);
  }

  /**
   * Validate that all the fields referenced exist.
   */
  public function validateEntity(array $form, FormStateInterface $form_state) {
    $entity = $form_state->getTemporaryValue('entity');

    // Assure that all the referenced columns can be located...
    $header = $this->spreadsheetService->getHeader($this->fileStorage->load(reset($entity->getSheet()['file'])));
    foreach ($entity->getMappings() as $name => $mapping) {
      $used_columns = iterator_to_array($this->migrationDeriver->getUsedColumns($mapping['mappings']));
      $intersection = array_intersect($used_columns, $header);
      if ($intersection == $used_columns) {
        // Looks good.
        continue;
      }
      else {
        $diff = array_diff($used_columns, $header);
        $form_state->setError($form['mappings'][$name], $this->t('Mapping references column(s) absent in given spreadsheet: %columns', [
          '%columns' => implode(', ', $diff),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity = $form_state->getTemporaryValue('entity');
  }

}
