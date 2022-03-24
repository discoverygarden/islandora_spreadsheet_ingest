<?php

namespace Drupal\islandora_spreadsheet_ingest_referential_integrity;

use Drupal\entity_reference_integrity\DependencyFieldMapGenerator as UpstreamDependencyFieldMapGenerator;
use Drupal\entity_reference_integrity\DependencyFieldMapGeneratorInterface;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

/**
 * Field map generator service.
 */
class DependencyFieldMapGenerator implements DependencyFieldMapGeneratorInterface {

  /**
   * The decorated service.
   *
   * @var \Drupal\entity_reference_integrity\DependencyFieldMapGeneratorInterface
   */
  protected DependencyFieldMapGeneratorInterface $inner;

  /**
   * THe FieldType plugin manager service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected FieldTypePluginManagerInterface $fieldTypePluginManger;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The memoized field map array.
   *
   * @var array
   */
  protected ?array $referentialFieldMap = NULL;

  /**
   * Constructor.
   */
  public function __construct(
    DependencyFieldMapGeneratorInterface $inner,
    FieldTypePluginManagerInterface $field_type_plugin_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->inner = $inner;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Helper; find other entity_reference type fields.
   *
   * @return \Traversable
   *   An iterator where keys are plugin IDs and the values are
   *   \Drupal\entity_reference_integrity\DependencyFieldMapGeneratorInterface
   *   instances for other entity_reference fields.
   */
  protected function generateReferenceFields() {
    foreach ($this->fieldTypePluginManager->getDefinitions() as $plugin_id => $definition) {
      if ($plugin_id === 'entity_reference') {
        // Accounted for in the "inner", so skip it here.
        continue;
      }
      elseif (is_a($definition['class'], EntityReferenceItem::class, TRUE)) {
        yield $plugin_id => new UpstreamDependencyFieldMapGenerator(
          $this->entityFieldManager,
          $this->entityTypeManager,
          $plugin_id,
          'target_type'
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReferentialFieldMap() {
    if ($this->referentialFieldMap === NULL) {
      $referential_field_map = $this->inner->getReferentialFieldMap();

      foreach ($this->generateReferenceFields() as $generator) {
        $referential_field_map = NestedArray::mergeDeep(
          $referential_field_map,
          $generator->getReferentialFieldMap()
        );
      }
      $this->referentialFieldMap = $referential_field_map;
    }

    return $this->referentialFieldMap;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencingFields($entity_type_id) {
    $map = $this->getReferentialFieldMap();
    return isset($map[$entity_type_id]) ? $map[$entity_type_id] : [];
  }

}
