<?php

namespace Drupal\Tests\islandora_spreadsheet_ingest\Unit;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\islandora_spreadsheet_ingest\FailureNotifier;
use Drupal\islandora_spreadsheet_ingest\MigrationGroupDeriverInterface;
use Drupal\islandora_spreadsheet_ingest\RequestInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the failure notifier.
 *
 * @group islandora_spreadsheet_ingest
 */
class FailureNotifierTest extends UnitTestCase {

  /**
   * Build a notifier with the given settings and migration definitions.
   */
  protected function getNotifier(array $settings, array $migrations = [], array $users = [], ?MailManagerInterface $mail = NULL) : FailureNotifier {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(fn($key) => $settings[$key] ?? NULL);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(fn($id) => $users[$id] ?? NULL);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($storage);

    $definitions = [];
    $instances = [];
    foreach ($migrations as $id => [$group, $failed]) {
      $definitions[$id] = ['migration_group' => $group];
      $id_map = $this->createMock(MigrateIdMapInterface::class);
      $id_map->method('errorCount')->willReturn($failed);
      $id_map->method('processedCount')->willReturn(10);
      $id_map->method('importedCount')->willReturn(10 - $failed);
      $migration = $this->createMock(MigrationInterface::class);
      $migration->method('getIdMap')->willReturn($id_map);
      $instances[$id] = $migration;
    }
    $plugin_manager = $this->createMock(PluginManagerInterface::class);
    $plugin_manager->method('getDefinitions')->willReturn($definitions);
    $plugin_manager->method('createInstance')->willReturnCallback(fn($id) => $instances[$id]);

    $deriver = $this->createMock(MigrationGroupDeriverInterface::class);
    $deriver->method('deriveName')->willReturn('isi__req');

    return new FailureNotifier(
      $config_factory,
      $entity_type_manager,
      $plugin_manager,
      $mail ?? $this->createMock(MailManagerInterface::class),
      $this->createMock(LanguageManagerInterface::class),
      $deriver,
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Build a mock request.
   */
  protected function getRequest($owner = NULL) : RequestInterface {
    $request = $this->createMock(RequestInterface::class);
    $request->method('id')->willReturn('req');
    $request->method('getOwner')->willReturn($owner);
    return $request;
  }

  /**
   * Only migrations in the request's group with failures are summarized.
   */
  public function testSummarize() : void {
    $notifier = $this->getNotifier([], [
      'isi__req_node' => ['isi__req', 3],
      'isi__req_file' => ['isi__req', 0],
      'isi__other_node' => ['isi__other', 5],
    ]);
    $summary = $notifier->summarize($this->getRequest());
    $this->assertCount(1, $summary);
    $this->assertSame('isi__req_node', $summary[0]['id']);
    $this->assertSame(3, $summary[0]['failed']);
  }

  /**
   * Recipients are de-duplicated and the owner is optional.
   */
  public function testRecipients() : void {
    $owner = $this->createMock('Drupal\user\UserInterface');
    $owner->method('getEmail')->willReturn('owner@example.com');

    $notifier = $this->getNotifier([
      'notifications.notify_owner' => TRUE,
      'notifications.recipients' => ['admin@example.com', 'owner@example.com'],
    ], [], [7 => $owner]);
    $this->assertSame(
      ['admin@example.com', 'owner@example.com'],
      $notifier->getRecipients($this->getRequest(7))
    );

    $notifier = $this->getNotifier([
      'notifications.notify_owner' => FALSE,
      'notifications.recipients' => ['admin@example.com'],
    ], [], [7 => $owner]);
    $this->assertSame(['admin@example.com'], $notifier->getRecipients($this->getRequest(7)));
  }

  /**
   * Nothing is sent when disabled, or when nothing went wrong.
   */
  public function testNoNotification() : void {
    $mail = $this->createMock(MailManagerInterface::class);
    $mail->expects($this->never())->method('mail');

    // Disabled, even with failures.
    $notifier = $this->getNotifier([], ['isi__req_node' => ['isi__req', 3]], [], $mail);
    $this->assertFalse($notifier->notify($this->getRequest(), 1));

    // Enabled, but clean run.
    $notifier = $this->getNotifier([
      'notifications.enabled' => TRUE,
      'notifications.recipients' => ['admin@example.com'],
    ], ['isi__req_node' => ['isi__req', 0]], [], $mail);
    $this->assertFalse($notifier->notify($this->getRequest(), 0));
  }

}
