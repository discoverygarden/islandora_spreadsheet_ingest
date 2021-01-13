<?php

namespace Drupal\islandora_spreadsheet_ingest\Model;

class Pipeline implements PipelineInterface {

  protected $source;
  protected $destinationName;
  protected $steps;

  public function __construct(SourceInterface $source, $dest_name) {
    $this->source = $source;
    $this->destinationName = $dest_name;
    $this->steps = [];
  }

  public function getSource() {
    return $this->source;
  }

  public function getDestinationName() {
    return $this->destinationName;
  }

  public function getName() {
    return "@{$this->destinationName}";
  }

  public function addStep(PipelineStepInterface $step) {
    if ($step->toProcessArray() == $this->source->toProcessArray()) {
      return;
    }
    $this->steps[] = $step;
  }

  public function removeStep(PipelineStepInterface $step) {
    $this->steps = array_filter(
      $this->steps,
      function ($candidate) use ($step) {
        return $step->toArray() != $candidate->toArray();
      }
    );
  }

  public function toProcessArray() {
    return [
      'plugin' => 'get',
      'source' => $this->getName(),
    ];
  }

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
