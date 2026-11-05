<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use Drupal\solo\Hook\SoloThemePreprocessHooks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Container ids and form element names may be arrays; Solo must not fatal.
 *
 * WHY THIS EXISTS
 * Issue #3630728 (Solo 1.0.36 with the filebrowser module): a container whose
 * attributes['id'] is an array made str_contains() throw a TypeError and the
 * page returned a 500. The same guard was shipped in 1.0.35 for #3609300 and
 * was lost when the procedural hooks were converted into src/Hook classes.
 * The contract: an array is reduced to its first element, and anything that is
 * not then a string is treated as empty.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class ArrayAttributeGuardTest extends TestCase {

  /**
   * Values for an id / #name and whether each should count as a match.
   *
   * @return array<string, array{mixed, bool}>
   *   The value, and whether it should be recognised.
   */
  public static function valueProvider(): array {
    return [
      'string' => ['x-ajax-wrapper', TRUE],
      'array with string' => [['x-ajax-wrapper'], TRUE],
      'empty array' => [[], FALSE],
      'associative array' => [['k' => 'v'], FALSE],
      'array with integer' => [[123], FALSE],
      'plain string, no match' => ['other', FALSE],
    ];
  }

  /**
   * The container hook adds ajax-container only for a recognised id.
   *
   * The docblock tag is for PHPUnit 9 (Drupal 10), which does not read the
   * attribute; PHPUnit 10+ reads the attribute and ignores the tag.
   *
   * @dataProvider valueProvider
   */
  #[DataProvider('valueProvider')]
  public function testContainerId(mixed $id, bool $expected): void {
    $variables = ['attributes' => ['id' => $id, 'class' => []]];
    SoloThemePreprocessHooks::preprocessContainer($variables);
    $this->assertSame($expected, in_array('ajax-container', $variables['attributes']['class'], TRUE));
  }

  /**
   * The form element hook adds solo-date-related only for a recognised name.
   *
   * The docblock tag is for PHPUnit 9 (Drupal 10), which does not read the
   * attribute; PHPUnit 10+ reads the attribute and ignores the tag.
   *
   * @dataProvider nameProvider
   */
  #[DataProvider('nameProvider')]
  public function testElementName(mixed $name, bool $expected): void {
    $variables = ['attributes' => ['class' => []], 'element' => ['#name' => $name]];
    SoloThemePreprocessHooks::preprocessFormElement($variables);
    $this->assertSame($expected, in_array('solo-date-related', $variables['attributes']['class'], TRUE));
  }

  /**
   * Same shapes as valueProvider(), with a date-like name as the match.
   *
   * @return array<string, array{mixed, bool}>
   *   The value, and whether it should be recognised.
   */
  public static function nameProvider(): array {
    return [
      'string' => ['field_created[0][value]', TRUE],
      'array with string' => [['field_created'], TRUE],
      'empty array' => [[], FALSE],
      'associative array' => [['k' => 'v'], FALSE],
      'array with integer' => [[123], FALSE],
      'plain string, no match' => ['title', FALSE],
    ];
  }

}
