<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Concrete implementaion of the pipeline interface.
 */
class Pipeline implements PipelineInterface {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The source instance.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\Model\SourceInterface
   */
  protected $source;

  /**
   * The name of the destination field/property.
   *
   * @var string
   */
  protected $destinationName;

  /**
   * The array of pipeline steps.
   *
   * @var \Drupal\islandora_spreadsheet_ingest\Model\PipelineStepInterface[]
   */
  protected $steps;

  /**
   * Constructor.
   */
  public function __construct(SourceInterface $source, $dest_name) {
    $this->source = $source;
    $this->destinationName = $dest_name;
    $this->steps = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationName() {
    return $this->destinationName;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return "@{$this->destinationName}";
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceName() {
    return $this->t('Processed value');
  }

  /**
   * {@inheritdoc}
   */
  public function addStep(PipelineStepInterface $step) {
    if ($step->toProcessArray() == $this->source->toProcessArray()) {
      return;
    }
    $this->steps[] = $step;
  }

  /**
   * {@inheritdoc}
   */
  public function removeStep(PipelineStepInterface $step) {
    $this->steps = array_filter(
      $this->steps,
      function ($candidate) use ($step) {
        return $step->toArray() != $candidate->toArray();
      }
    );
  }

  /**
   * {@inheritdoc}
   */
  public function toProcessArray() {
    return [
      'plugin' => 'get',
      'source' => $this->getName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toPipelineArray() {
    $to_return = [
      $this->source->toProcessArray(),
    ];

    foreach ($this->steps as $step) {
      $to_return[] = $step->toProcessArray();
    }

    return $to_return;
  }

}
