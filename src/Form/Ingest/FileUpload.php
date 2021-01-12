<?php

namespace Drupal\islandora_spreadsheet_ingest\Form\Ingest;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Url;

use Symfony\Component\DependencyInjection\ContainerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\islandora_spreadsheet_ingest\Spreadsheet\ChunkReadFilter;

/**
 * Form for setting up ingests.
 */
class FileUpload extends FormBase {

  protected $entityTypeManager;

  /**
   * Is entity_type.manager service for `file`.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileEntityStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileEntityStorage = $instance->entityTypeManager->getStorage('file');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_spreadsheet_ingest_file_upload_form';
  }

  protected function getTargetFile(FormStateInterface $form_state) {
    $target_file = $form_state->getValue('target_file');
    if ($target_file) {
      return $this->fileEntityStorage->load(reset($target_file));
    }
    throw new \Exception('No target file selected.');

  }

  protected function getSpreadsheetReader(FormStateInterface $form_state) {
    $target_file = $form_state->getValue('target_file');
    if ($target_file) {
      $reader = IOFactory::createReaderForFile($this->getTargetFile($form_state)->uri->first()->getString());
      // XXX: Not really dealing with writing here... might as well inform 
      $reader->setReadDataOnly(TRUE);
      return $reader;
    }
    throw new \Exception('No target file from which to create a reader.');
  }

  protected function getSpreadsheetOptions(FormStateInterface $form_state) {
    $target_file = $form_state->getValue('target_file');
    if ($target_file) {
      $reader = $this->getSpreadsheetReader($form_state);;
      $lister = [$reader, 'listWorksheetNames'];
      return is_callable($lister) ?
        call_user_func($lister, $target_file) :
        // XXX: Need to provide _some_ name for things like CSVs.
        [$this->t('Irrelevant/single-sheet format')];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form += parent::buildForm($form, $form_state);

    $target_file = $form_state->getValue('target_file');
    $sheet_options = $this->getSpreadsheetOptions($form_state);
    $form['target_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Target file'),
      '#upload_validators' => [
        'file_validate_extensions' => ['xlsx xlsm xltx xltm xls xlt ods ots slk xml gnumeric htm html csv'],
      ],
      'sheet' => [
        '#type' => 'select',
        '#title' => $this->t('Sheet'),
        '#empty_value' => '-\\_/- select -/_\\-',
        '#options' => $sheet_options,
        '#default_value' => count($sheet_options) === 1 ? key($sheet_options) : NULL,
        '#states' => [
          'visible' => [
            ':input[name="target_file[fids]"]' => [
              'filled' => TRUE,
            ],
          ],
        ],
      ],
    ];

    $form['actions'] += [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
      ],
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('target_file', $form_state->getValue('target_file'));
    $this->store->set('spreadsheet', $form_state->getValue('sheet'));

    $form_state->setRedirect('islandora_spreadsheet_ingest.mapping');
  }

}
