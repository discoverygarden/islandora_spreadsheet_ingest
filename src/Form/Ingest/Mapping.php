<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for setting up ingests.
 */
class Mapping extends EntityForm {

  use MigrationTrait;

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
      '#value' => $this->t('Save and review'),
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
      'entity.isi_request.view',
      [
        'isi_request' => $this->entity->id(),
      ],
      $options
    );
  }

}
