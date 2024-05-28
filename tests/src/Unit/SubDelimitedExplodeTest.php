<?php

namespace Drupal\Tests\islandora_spreadsheet_ingest\Unit;

use Drupal\islandora_spreadsheet_ingest\Plugin\migrate\process\SubDelimitedExplode;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;

/**
 * Test exploding subdelimiter strings.
 *
 * @group islandora_spreadsheet_ingest
 */
class SubDelimitedExplodeTest extends UnitTestCase {

  /**
   * Mock executable for test execution.
   *
   * @var \Drupal\migrate\MigrateExecutableInterface
   */
  protected $mockExecutable;

  /**
   * Mock row for test execution.
   *
   * @var \Drupal\migrate\Row
   */
  protected $mockRow;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->mockExecutable = $this->getMockBuilder(MigrateExecutableInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->mockRow = $this->getMockBuilder(Row::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Test misc configs.
   *
   * @dataProvider baseConfigProvider
   */
  public function testBase($desc, $config, $string, $expected) {
    $instance = new SubDelimitedExplode($config, [], []);

    $output = $instance->transform($string, $this->mockExecutable, $this->mockRow, $this->randomMachineName());

    $this->assertEquals($expected, $output, "$desc, as expected.");
  }

  /**
   * Data provider for base test.
   */
  public function baseConfigProvider() {
    return [
      [
        'Base explode',
        ['delimiter' => ';', 'subdelimiter' => '|'],
        'A|alpha;B|bravo',
        [
          ['A', 'alpha'],
          ['B', 'bravo'],
        ],
      ],
      [
        'Base explode with different delimiters',
        ['delimiter' => '#', 'subdelimiter' => '^'],
        'A^alpha#B^bravo',
        [
          ['A', 'alpha'],
          ['B', 'bravo'],
        ],
      ],
      [
        'Base explode with keys and no trimming',
        [
          'delimiter' => ';',
          'subdelimiter' => '|',
          'keys' => ['a', 'b'],
          'trim' => FALSE,
          'subtrim' => FALSE,
        ],
        'A|alpha;B|bravo',
        [
          ['a' => 'A', 'b' => 'alpha'],
          ['a' => 'B', 'b' => 'bravo'],
        ],
      ],
      [
        'Base explode with keys',
        ['delimiter' => ';', 'subdelimiter' => '|', 'keys' => ['a', 'b']],
        'A|alpha;B|bravo',
        [
          ['a' => 'A', 'b' => 'alpha'],
          ['a' => 'B', 'b' => 'bravo'],
        ],
      ],
      [
        'Base explode with insufficient keys',
        ['delimiter' => ';', 'subdelimiter' => '|', 'keys' => ['a']],
        'A|alpha;B|bravo',
        [
          ['a' => 'A', 1 => 'alpha'],
          ['a' => 'B', 1 => 'bravo'],
        ],
      ],
      [
        'Base explode, with full trimming',
        [
          'delimiter' => ';',
          'subdelimiter' => '|',
          'trim' => TRUE,
          'subtrim' => TRUE,
        ],
        'A | alpha ; B |bravo',
        [
          ['A', 'alpha'],
          ['B', 'bravo'],
        ],
      ],
      [
        'Base explode, with only subtrimming',
        [
          'delimiter' => ';',
          'subdelimiter' => '|',
          'trim' => FALSE,
          'subtrim' => TRUE,
        ],
        'A | alpha ; B |bravo',
        [
          ['A', 'alpha'],
          ['B', 'bravo'],
        ],
      ],
      [
        'Base explode, with only top trimming',
        [
          'delimiter' => ';',
          'subdelimiter' => '|',
          'trim' => TRUE,
          'subtrim' => FALSE,
        ],
        'A | alpha ; B |bravo',
        [
          ['A ', ' alpha'],
          ['B ', 'bravo'],
        ],
      ],
    ];
  }

}
