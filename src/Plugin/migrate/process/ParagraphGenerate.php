<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\migrate\process;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generate Paragraph entities.
 *
 * @MigrateProcessPlugin(
 *   id = "isi_paragraph_generate"
 * )
 *
 * @code
 * field_paragraphs:
 *   - plugin: isi_paragraph_generate
 *     type: paragraph_bundle_type
 *     values:
 *       field_one: col_one
 *       field_two: col_two
 *       field_three: "@something_built"
 * @endcode
 *
 */
class ParagraphGenerate extends ProcessPluginBase implements ConfigurableInterface, ContainerFactoryPluginInterface {

  protected $getProcessPlugin;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);

    assert(!empty($configuration['type']));
    assert(!empty($configuration['values']));

    $instance->getProcessPlugin = $container->get('plugin.manager.migrate.process')->createInstance('get', [
      'source' => $instance->getConfiguration(),
    ]);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $config) {
    $this->configuration = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $paragraph = Paragraph::create(
      [
      'type' => $this->configuration['type'],
      ] +
      $this->mapValues($migrate_executable, $row)
    );
    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

  /**
   * Map requested fields.
   *
   * @param \Drupal\migrate\MigrateExecutableInterface $executable
   *   The migration exectuable.
   * @param \Drupal\migrate\Row $row
   *   The row object being processed.
   *
   * @return array
   *   An associative array with the mapped values.
   */
  protected function mapValues(MigrateExecutableInterface $executable, Row $row) {
    $mapped = [];

    foreach ($this->getConfiguration()['values'] as $key => $property) {
      $mapped[$key] = $row->get($property);
    }

    return $mapped;
  }

}
