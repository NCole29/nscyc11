<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use Drupal\solo\SocialIconColor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the social-icon colour rules.
 *
 * The setting is printed into a style attribute, so a bare custom property
 * name — "--solo-fb" rather than "var(--solo-fb)" — makes CSS discard the
 * whole declaration and the icons render grey, with nothing logged. Commit
 * 6827481 fixed that in the templates' own fallback; these rules close the
 * half where the value comes from the site builder.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[CoversClass(SocialIconColor::class)]
#[Group('solo')]
class SocialIconColorTest extends TestCase {

  /**
   * Values that are a bare custom property name.
   *
   * @return array<string, array{string}>
   *   Test cases.
   */
  public static function bareCustomProperties(): array {
    return [
      'short name' => ['--fb'],
      'theme name' => ['--solo-fb'],
      'underscores' => ['--solo_fb_2'],
      'leading and trailing space' => ['  --solo-fb  '],
    ];
  }

  /**
   * Values that are NOT a bare custom property name.
   *
   * @return array<string, array{string}>
   *   Test cases.
   */
  public static function notBareCustomProperties(): array {
    return [
      'empty' => [''],
      'hex' => ['#1877f2'],
      'short hex' => ['#abc'],
      'already wrapped' => ['var(--solo-fb)'],
      'single dash' => ['-solo-fb'],
      'plain word' => ['red'],
    ];
  }

  /**
   * A bare custom property name is recognised as such.
   *
   * The docblock tag is for PHPUnit 9 (Drupal 10), which does not read the
   * attribute; PHPUnit 10+ reads the attribute and ignores the tag.
   *
   * @dataProvider bareCustomProperties
   */
  #[DataProvider('bareCustomProperties')]
  public function testBareCustomPropertyIsDetected(string $value): void {
    $this->assertTrue(SocialIconColor::isBareCustomProperty($value));
    $this->assertFalse(
      SocialIconColor::isAcceptable($value),
      'A bare custom property name is never an acceptable value.'
    );
  }

  /**
   * Anything else is not mistaken for a bare custom property name.
   *
   * @dataProvider notBareCustomProperties
   */
  #[DataProvider('notBareCustomProperties')]
  public function testOtherValuesAreNotBareCustomProperties(string $value): void {
    $this->assertFalse(SocialIconColor::isBareCustomProperty($value));
  }

  /**
   * The values the setting accepts.
   */
  public function testAcceptedValues(): void {
    $this->assertTrue(SocialIconColor::isAcceptable(''), 'Empty means use the default.');
    $this->assertTrue(SocialIconColor::isAcceptable('   '), 'Whitespace only is empty.');
    $this->assertTrue(SocialIconColor::isAcceptable('#1877f2'));
    $this->assertTrue(SocialIconColor::isAcceptable('#ABC'));
    $this->assertTrue(SocialIconColor::isAcceptable('var(--solo-fb)'));
    $this->assertTrue(SocialIconColor::isAcceptable('var( --solo-fb )'), 'Inner spacing is tolerated.');
  }

  /**
   * The values the setting refuses.
   */
  public function testRefusedValues(): void {
    $this->assertFalse(SocialIconColor::isAcceptable('--solo-fb'), 'The bug this exists for.');
    $this->assertFalse(SocialIconColor::isAcceptable('#12345'), 'Five hex digits is not a colour.');
    $this->assertFalse(SocialIconColor::isAcceptable('red'), 'Named colours are out of scope here.');
    $this->assertFalse(SocialIconColor::isAcceptable('var(--a); background:url(x)'), 'No trailing CSS.');
    $this->assertFalse(SocialIconColor::isAcceptable('"><script>alert(1)</script>'), 'No markup.');
  }

  /**
   * The suggestion shows the site builder exactly what to type.
   */
  public function testSuggestWrapsOnlyBareNames(): void {
    $this->assertSame('var(--solo-fb)', SocialIconColor::suggest('--solo-fb'));
    $this->assertSame('var(--solo-fb)', SocialIconColor::suggest('  --solo-fb  '));
    $this->assertSame('#1877f2', SocialIconColor::suggest('#1877f2'), 'A valid value is returned unchanged.');
    $this->assertSame('var(--solo-fb)', SocialIconColor::suggest('var(--solo-fb)'), 'Not double wrapped.');
  }

}
