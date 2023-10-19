<?php

namespace Drupal\islandora_spreadsheet_ingest\Commands;

use Drupal\Core\Entity\EntityFieldManagerInterface;

use Drush\Commands\DrushCommands;

/**
 * Migration tools command.
 */
class ToolsCommands extends DrushCommands {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $cacheKey;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct();
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Provides CSV headers and migration yaml for a bundle.
   *
   * @param string $bundle_name
   *   Bundle name to generate info for.
   *
   * @command islandora_spreadsheet_ingest:generate-bundle-info
   * @usage islandora_spreadsheet_ingest:generate-bundle-info islandora_object
   *   Displays CSV headers and migration yaml for the islandora_object bundle.
   */
  public function generateBundleInfo($bundle_name) {
    $base_fields = [
      'ID',
      'Member_of',
      'Model',
      'Digital_File',
      'Title',
      'Description',
      'Mime',
      'Date_created',
    ];
    $filter_fields = [
      'field_member_of',
      'field_model',
      'field_representative_image',
      'field_description',
    ];

    $raw_bundle_info = $this->entityFieldManager->getFieldDefinitions('node', $bundle_name);
    $raw_bundle_fields = array_keys($raw_bundle_info);

    $dynamic_bundle_fields = array_filter(
      $raw_bundle_fields,
      function ($field) {
        return substr($field, 0, 6) == 'field_';
      }
    );
    $dynamic_csv_headers = array_diff($dynamic_bundle_fields, $filter_fields);
    $csv_headers = $base_fields + $dynamic_csv_headers;

    $this->output()->writeln("START FULL HEADER INFO\n");
    $this->output()->writeln(implode(',', $csv_headers));
    $this->output()->writeln("\nSTOP FULL HEADER INFO\n");

    $this->output()->writeln("START DYNAMIC MIGRATION INFO\n");
    foreach ($dynamic_csv_headers as $header) {
      $this->output()->writeln("    - '$header'");
    }
    $this->output()->writeln("\nSEP DYNAMIC MIGRATION INFO\n");
    foreach ($dynamic_csv_headers as $header) {
      $this->output()->writeln("  $header: $header");
    }
    $this->output()->writeln("\nSTOP DYNAMIC MIGRATION INFO\n");
  }

}
