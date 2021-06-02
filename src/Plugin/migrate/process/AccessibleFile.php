<?php

namespace Drupal\islandora_spreadsheet_ingest\Plugin\migrate\process;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates that a given filepath is accessible given configured constraints.
 *
 * The source value is in the form of '/path/to/foo.txt' or 'public://bar.txt'.
 *
 * Examples:
 *
 * @code
 * process:
 *   path_to_file:
 *     plugin: file_is_accessible
 *     source: /path/to/file.png
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "file_is_accessible"
 * )
 */
class AccessibleFile extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a file_is_accessible process plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrappers
   *   The stream wrapper manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StreamWrapperManagerInterface $stream_wrappers, FileSystemInterface $file_system, ConfigFactoryInterface $config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $stream_wrappers;
    $this->configFactory = $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $allowed_paths = $this->configFactory->get('islandora_spreadsheet_ingest.settings')->get('binary_directory_whitelist');
    $allowed_schemes = $this->configFactory->get('islandora_spreadsheet_ingest.settings')->get('schemes');

    // If it's a local path check to see if it's in our allowed list.
    $file_path = $this->fileSystem->realpath($value);
    if ($file_path) {
      foreach ($allowed_paths as $path) {
        if (strpos($file_path, $path) === 0) {
          return $value;
        }
      }
    }

    // If the file has a scheme check to see if it's in our allowed list.
    $file_scheme = $this->streamWrapperManager->getScheme($value);
    if ($file_scheme) {
      foreach ($allowed_schemes as $scheme) {
        if ($file_scheme === $scheme) {
          return $value;
        }
      }
    }
    throw new MigrateSkipRowException(strtr("The file provided (:file) is not allowed within the configured approved list of directories or schemes.", [
      ':file' => $value,
    ]));
  }

}
