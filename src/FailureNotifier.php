<?php

namespace Drupal\islandora_spreadsheet_ingest;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\migrate\Plugin\MigrationInterface;
use Psr\Log\LoggerInterface;

/**
 * Notify interested parties of failed or partially failed deferred ingests.
 */
class FailureNotifier {

  /**
   * Constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PluginManagerInterface $migrationPluginManager,
    protected MailManagerInterface $mailManager,
    protected LanguageManagerInterface $languageManager,
    protected MigrationGroupDeriverInterface $migrationGroupDeriver,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Notify about the outcome of a request's ingest, if there is a problem.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The request that was processed.
   * @param int|null $exit_code
   *   The exit code of the ingest process; NULL if not known.
   *
   * @return bool
   *   TRUE if a notification was sent to at least one recipient; FALSE
   *   otherwise (disabled, nothing wrong, or no recipients).
   */
  public function notify(RequestInterface $request, ?int $exit_code = NULL) : bool {
    $config = $this->configFactory->get('islandora_spreadsheet_ingest.settings');
    if (!$config->get('notifications.enabled')) {
      return FALSE;
    }

    $summary = $this->summarize($request);
    $failed = array_sum(array_column($summary, 'failed'));
    $process_failed = $exit_code !== NULL && $exit_code !== 0;
    if (!$failed && !$process_failed) {
      return FALSE;
    }

    $recipients = $this->getRecipients($request);
    if (!$recipients) {
      $this->logger->warning('Ingest problems for request {id}, but there are no notification recipients.', [
        'id' => $request->id(),
      ]);
      return FALSE;
    }

    $group = $this->migrationGroupDeriver->deriveName($request);
    $params = [
      'request_id' => $request->id(),
      'request_label' => $request->label(),
      'exit_code' => $exit_code,
      'summary' => $summary,
      'messages_url' => Url::fromRoute('entity.migration.list', [
        'migration_group' => $group,
      ], ['absolute' => TRUE])->toString(),
    ];
    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    $sent = FALSE;
    foreach ($recipients as $to) {
      $result = $this->mailManager->mail('islandora_spreadsheet_ingest', 'ingest_failure', $to, $langcode, $params);
      $sent = $sent || !empty($result['result']);
    }
    return $sent;
  }

  /**
   * Gather the per-migration failure counts for a request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The request for which to gather counts.
   *
   * @return array[]
   *   Rows for each migration with at least one failed row, each with the
   *   keys "id", "processed", "imported", "failed".
   */
  public function summarize(RequestInterface $request) : array {
    $group = $this->migrationGroupDeriver->deriveName($request);
    $rows = [];
    foreach ($this->migrationPluginManager->getDefinitions() as $id => $definition) {
      if (($definition['migration_group'] ?? NULL) !== $group) {
        continue;
      }
      $migration = $this->migrationPluginManager->createInstance($id);
      if (!$migration instanceof MigrationInterface) {
        continue;
      }
      $id_map = $migration->getIdMap();
      $failed = $id_map->errorCount();
      if ($failed) {
        $rows[] = [
          'id' => $id,
          'processed' => $id_map->processedCount(),
          'imported' => $id_map->importedCount(),
          'failed' => $failed,
        ];
      }
    }
    return $rows;
  }

  /**
   * Determine the email addresses to notify for the given request.
   *
   * @param \Drupal\islandora_spreadsheet_ingest\RequestInterface $request
   *   The request in question.
   *
   * @return string[]
   *   The unique addresses to notify.
   */
  public function getRecipients(RequestInterface $request) : array {
    $config = $this->configFactory->get('islandora_spreadsheet_ingest.settings');
    $recipients = $config->get('notifications.recipients') ?? [];
    if ($config->get('notifications.notify_owner')) {
      $owner = $request->getOwner() ?
        $this->entityTypeManager->getStorage('user')->load($request->getOwner()) :
        NULL;
      if ($owner && $owner->getEmail()) {
        $recipients[] = $owner->getEmail();
      }
    }
    return array_values(array_unique(array_filter($recipients)));
  }

}
