<?php

namespace Drupal\islandora_spreadsheet_ingest\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\islandora_spreadsheet_ingest\RequestInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Request entity.
 *
 * @ContentEntityType(
 *   id = "isi_request",
 *   label = @Translation("Islandora Spreadsheet Ingest Request"),
 *   handlers = {
 *     "storage_schema" = "Drupal\islandora_spreadsheet_ingest\RequestStorageSchema",
 *     "list_builder" = "Drupal\islandora_spreadsheet_ingest\Controller\RequestListBuilder",
 *     "form" = {
 *       "process" = "Drupal\islandora_spreadsheet_ingest\Form\Ingest\Review",
 *       "add" = "Drupal\islandora_spreadsheet_ingest\Form\Ingest\FileUpload",
 *       "delete" = "Drupal\islandora_spreadsheet_ingest\Form\RequestDeleteForm",
 *       "edit" = "Drupal\islandora_spreadsheet_ingest\Form\Ingest\FileUpload",
 *       "view" = "Drupal\islandora_spreadsheet_ingest\Form\Ingest\Review",
 *     },
 *     "access" = "Drupal\islandora_spreadsheet_ingest\RequestAccessControlHandler",
 *     "view_builder" = "Drupal\islandora_spreadsheet_ingest\RequestViewBuilder",
 *   },
 *   admin_permission = "administer islandora_spreadsheet_ingest requests",
 *   base_table = "islandora_spreadsheet_ingest_request",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *     "owner" = "owner",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/islandora_spreadsheet_ingest/{isi_request}",
 *     "process-form" = "/admin/content/islandora_spreadsheet_ingest/{isi_request}/process",
 *     "edit-form" = "/admin/content/islandora_spreadsheet_ingest/{isi_request}/edit",
 *     "map-form" = "/admin/content/islandora_spreadsheet_ingest/{isi_request}/mapping",
 *     "delete-form" = "/admin/content/islandora_spreadsheet_ingest/{isi_request}/delete",
 *   }
 * )
 */
class Request extends ContentEntityBase implements EntityOwnerInterface, RequestInterface {

  use EntityOwnerTrait {
    getOwner as traitGetOwner;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalMapping() {
    return $this->get('original_mapping')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getActive() {
    return $this->get('active')->first()->getValue()['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSheet() {
    return [
      'file' => [$this->get('sheet_file')->first()->getString()],
      'sheet' => $this->get('sheet_sheet')->first()->getString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMappings() {
    return $this->get('mappings')->first()->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->traitGetOwner()->id();
  }

  /**
   * {@inheritDoc}
   */
  public function set($name, $value, $notify = TRUE) {
    if ($name === 'sheet') {
      \trigger_deprecation('discoverygarden/islandora_spreadsheet_ingest', '4.0.0', 'Setting `sheet` directly is deprecated as of 4.0.0; instead set `sheet_file` and `sheet_sheet` individually.');
      return $this->set('sheet_file', $value['file'] ?? [], $notify)
        ->set('sheet_sheet', $value['sheet'] ?? '', $notify);
    }
    if ($name === 'originalMapping') {
      \trigger_deprecation('discoverygarden/islandora_spreadsheet_ingest', '4.0.0', '`originalMapping` has been renamed to `original_mapping` as of 4.0.0.');
      return $this->set('original_mapping', $value, $notify);
    }
    return parent::set($name, $value, $notify);
  }

  /**
   * {@inheritDoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(\t('Title'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setCardinality(1);
    $fields['machine_name'] = BaseFieldDefinition::create('string')
      ->setLabel(\t('Machine Name'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setCardinality(1)
      ->addConstraint('UniqueField', [
        'message' => 'The machine_name %value is already in use.',
      ]);
    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(\t('Label'))
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE);
    $fields['sheet_file'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(\t('Spreadsheet file'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setSetting('target_type', 'file')
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
      ])
      ->setCardinality(1);
    $fields['sheet_sheet'] = BaseFieldDefinition::create('string')
      ->setLabel(\t('Worksheet'))
      ->setDescription(\t('The specific worksheet if the file corresponds to an ODS/XLSX which is possible of containing multiple.'))
      ->setCardinality(1);
    $fields['mappings'] = BaseFieldDefinition::create('map')
      ->setLabel(\t('Mappings'))
      ->setDescription(\t('Migration mappings'))
      ->setCardinality(1);
    $fields['original_mapping'] = BaseFieldDefinition::create('string')
      ->setLabel(\t('Original mapping'))
      ->setDescription(\t('Original migration template'))
      ->setCardinality(1);

    return $fields;
  }

}
