<?php

use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\views_row_sdc\Plugin\views\row\ComponentRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit test of boolean function.
 */
#[CoversClass(ComponentRow::class)]
#[CoversFunction('booleanProp')]
#[CoversFunction('integerProp')]
#[CoversFunction('stringProp')]
class ComponentRowPropTest extends TestCase {

  /**
   * Create Row plugin class through child class.
   */
  protected function getPluginClass(): ComponentRow {
    $componentManager = $this->createStub(ComponentPluginManager::class);

    return new ComponentRow([], 'component_row', [], $componentManager);
  }

  /**
   * Test boolean check function returns expected values.
   */
  #[Group('views_row_sdc')]
  #[DataProvider('booleanProvider')]
  public function testBooleanProp($input, $expected) {
    // Get ComponentRow class and call booleanProp through reflection.
    $plugin = $this->getPluginClass();
    $booleanProp = new \ReflectionMethod($plugin, 'booleanProp');
    $actual = $booleanProp->invokeArgs($plugin, [$input]);
    $this->assertSame($expected, $actual);
  }

  /**
   * Test values for calling boolean function.
   */
  public static function booleanProvider(): array {
    return [
      ['1', TRUE],
      ['true', TRUE],
      ['TRUE', TRUE],
      ['yes', TRUE],
      ['0', FALSE],
      ['false', FALSE],
      ['no', FALSE],
      ['', FALSE],
      [' True ', TRUE],
      [' YES ', TRUE],
      ['ON ', TRUE],
      ['Off', FALSE],
    ];
  }

  /**
   * Test boolean check function returns expected values.
   */
  #[Group('views_row_sdc')]
  #[DataProvider('integerProvider')]
  public function testIntegerProp($input, $expected) {
    // Get ComponentRow class and call integerProp through reflection.
    $plugin = $this->getPluginClass();
    $integerProp = new \ReflectionMethod($plugin, 'integerProp');
    $actual = $integerProp->invokeArgs($plugin, [$input]);
    $this->assertSame($expected, $actual);
  }

  /**
   * Test values for calling boolean function.
   */
  public static function integerProvider(): array {
    return [
      ['1', 1],
      ['023', 23],
      ['-13', -13],
      ['0', 0],
      ['false', 0],
      ['no value', 0],
      ['', 0],
      [' 15 ', 15],
    ];
  }

  /**
   * Test boolean check function returns expected values.
   */
  #[Group('views_row_sdc')]
  #[DataProvider('stringProvider')]
  public function testStringProp($prop, $raster, $raw, $expected) {
    // Get ComponentRow class and call stringProp through reflection.
    $plugin = $this->getPluginClass();
    $stringProp = new \ReflectionMethod($plugin, 'stringProp');
    $actual = $stringProp->invokeArgs($plugin, [$prop, $raster, $raw]);
    $this->assertSame($expected, $actual);
  }

  /**
   * Test values for calling boolean function.
   */
  public static function stringProvider(): array {
    return [
      [['type' => 'string', 'enum' => ['test']], 'Decorated Test', 'test', 'test'],
      [['type' => 'string', 'enum' => ['match']], 'Mismatch', 'mismatch', 'mismatch'],
      [['type' => 'string', 'enum' => []], '', '', ''],
      [['type' => 'string'], 'Comfort', 'comfort', 'Comfort'],
      [['type' => 'string'], '<span>Magic</span>', 'magic', '<span>Magic</span>'],
      [['type' => 'string'], '', '', ''],
    ];
  }

}
