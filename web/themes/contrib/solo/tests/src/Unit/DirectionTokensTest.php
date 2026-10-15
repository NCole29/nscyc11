<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the direction contract in the theme's CSS.
 *
 * Solo expresses "the start side" two different ways, and each is correct for
 * a different job:
 *
 * - --solo-text-align-start / --solo-text-align-end are PHYSICAL (left/right).
 *   They are right for a DECLARATION on an element — a table cell, a bar item,
 *   a float — because the element resolves them where it sits.
 * - text-align on <body> is INHERITED. A physical value there is copied down
 *   into every descendant, so a dir="rtl" block inside an LTR page inherited
 *   "left" and rendered left-aligned. The logical keyword "start" inherits as
 *   a keyword and is resolved per element against that element's own
 *   direction, which is the behaviour wanted.
 *
 * Measured on sts.local before the fix: a dir="rtl" block inside an LTR page
 * got direction:rtl but text-align:left, and so did a .w3-table cell inside it.
 * After: both right, an LTR island nested inside them back to left, and a
 * dir="rtl" child of a text-align:center block still centred.
 *
 * :root:dir(rtl) can only ever match <html>, which is why the element-level
 * rules are needed as well as — not instead of — it.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class DirectionTokensTest extends TestCase {

  /**
   * The theme root.
   */
  protected function themePath(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Reads a CSS file from the theme, with the comments stripped.
   */
  protected function css(string $relative): string {
    $path = $this->themePath() . '/' . $relative;
    $this->assertFileExists($path, "$relative is missing from the theme.");
    $raw = file_get_contents($path);
    $this->assertIsString($raw);
    return (string) preg_replace('#/\*.*?\*/#s', '', $raw);
  }

  /**
   * Returns the declaration block for the first rule with this exact selector.
   */
  protected function block(string $css, string $selector): ?string {
    $pattern = '#(?:^|[{}])\s*' . preg_quote($selector, '#') . '\s*\{([^{}]*)\}#m';
    return preg_match($pattern, $css, $m) ? $m[1] : NULL;
  }

  /**
   * The body element must align logically, so inheritance stays correct.
   */
  public function testBodyAlignsLogicallyNotPhysically(): void {
    $block = $this->block($this->css('css/base/solo-normalize.css'), 'body');
    $this->assertNotNull($block, 'solo-normalize.css has no body rule.');
    $this->assertMatchesRegularExpression(
      '/text-align\s*:\s*start\s*;/',
      $block,
      'body must use the logical keyword "start".'
    );
    $this->assertDoesNotMatchRegularExpression(
      '/text-align\s*:\s*var\(\s*--solo-text-align-start\s*\)/',
      $block,
      'body must NOT use the physical token: text-align inherits, so every '
      . 'descendant would inherit the page direction and a dir="rtl" block '
      . 'inside an LTR page would stay left-aligned.'
    );
  }

  /**
   * The page-level path must survive — the element-level rules add to it.
   */
  public function testRootLevelDirectionOverrideIsStillPresent(): void {
    $block = $this->block($this->css('css/base/solo-variables.css'), ':root:dir(rtl)');
    $this->assertNotNull($block, ':root:dir(rtl) was removed.');
    $this->assertMatchesRegularExpression('/--solo-text-align-start\s*:\s*right\s*;/', $block);
    $this->assertMatchesRegularExpression('/--solo-text-align-end\s*:\s*left\s*;/', $block);
  }

  /**
   * The default (LTR) token values must be declared on :root.
   */
  public function testRootDefaultsArePhysicalLtr(): void {
    $block = $this->block($this->css('css/base/solo-variables.css'), ':root');
    $this->assertNotNull($block, ':root has no declaration block.');
    $this->assertMatchesRegularExpression('/--solo-text-align-start\s*:\s*left\s*;/', $block);
    $this->assertMatchesRegularExpression('/--solo-text-align-end\s*:\s*right\s*;/', $block);
  }

  /**
   * Element-level direction must flip the tokens, in both directions.
   *
   * Without the ltr half, an LTR island inside an RTL page stays flipped.
   */
  public function testElementLevelDirectionFlipsTheTokens(): void {
    $css = $this->css('css/base/solo-variables.css');

    $rtl = $this->block($css, ':where(:dir(rtl))');
    $this->assertNotNull(
      $rtl,
      ':where(:dir(rtl)) is missing. :root:dir(rtl) only ever matches <html>, '
      . 'so without this a dir="rtl" element keeps the page tokens.'
    );
    $this->assertMatchesRegularExpression('/--solo-text-align-start\s*:\s*right\s*;/', $rtl);
    $this->assertMatchesRegularExpression('/--solo-text-align-end\s*:\s*left\s*;/', $rtl);

    $ltr = $this->block($css, ':where(:dir(ltr))');
    $this->assertNotNull(
      $ltr,
      ':where(:dir(ltr)) is missing — an LTR island inside an RTL page needs it.'
    );
    $this->assertMatchesRegularExpression('/--solo-text-align-start\s*:\s*left\s*;/', $ltr);
    $this->assertMatchesRegularExpression('/--solo-text-align-end\s*:\s*right\s*;/', $ltr);
  }

  /**
   * The element-level rules must stay at zero specificity.
   *
   * A bare :dir(rtl) is (0,1,0) and would outrank a plain class selector in a
   * sub-theme or a colour scheme. :where() keeps it at (0,0,0).
   */
  public function testElementLevelRulesAreZeroSpecificity(): void {
    $css = $this->css('css/base/solo-variables.css');
    preg_match_all('#(?:^|[{}])\s*([^{}]*:dir\((?:ltr|rtl)\)[^{}]*)\{#m', $css, $m);
    $this->assertNotEmpty($m[1], 'No :dir() rules found at all.');
    foreach ($m[1] as $selector) {
      $selector = trim($selector);
      // :root:dir(rtl) is the one deliberate exception: it must outrank the
      // :root defaults directly above it, and it only ever matches <html>.
      if ($selector === ':root:dir(rtl)') {
        continue;
      }
      $this->assertStringStartsWith(
        ':where(',
        $selector,
        "Selector '$selector' must be wrapped in :where() to stay at zero "
        . 'specificity.'
      );
    }
  }

  /**
   * Components with no RTL rules must not pin themselves to a physical side.
   *
   * These three carry no [dir=rtl] block of their own, so a physical
   * margin-left / padding-left / left is never mirrored and the component
   * renders on the wrong side in an RTL page. Measured on sts.local before the
   * fix: ul.sitemap kept a 40px padding-right in RTL, because padding-left: 0
   * zeroed the side the RTL list was not using.
   *
   * Scoped to the files that were fixed rather than the whole theme: the other
   * components either carry their own [dir=rtl] rules or use physical sides
   * deliberately, and .solo-back-to-top--bottom-right means bottom-right in
   * both directions by design.
   */
  public function testFixedComponentsUseLogicalOffsets(): void {
    $files = [
      'css/components/solo-sitemap.css',
      'css/components/solo-file-upload.css',
      'css/components/solo-preloader.css',
    ];
    // .solo-file-native is width:100% height:100%, so it covers its wrapper
    // from either side; left: 0 there is not a direction bug.
    $allowed = ['.solo-file-native'];

    $offenders = [];
    foreach ($files as $relative) {
      $css = $this->css($relative);
      foreach (preg_split('/(?<=\})/', $css) ?: [] as $rule) {
        if (!str_contains($rule, '{')) {
          continue;
        }
        [$selector, $body] = explode('{', $rule, 2);
        $selector = trim(preg_replace('/\s+/', ' ', $selector) ?? '');
        foreach ($allowed as $skip) {
          if (str_contains($selector, $skip)) {
            continue 2;
          }
        }
        $pattern = '/(?:^|;)\s*((?:margin|padding|border)-(?:left|right)|left|right)\s*:/';
        if (preg_match_all($pattern, $body, $m)) {
          foreach ($m[1] as $prop) {
            $offenders[] = sprintf('%s -> %s { %s: ... }', $relative, $selector, $prop);
          }
        }
      }
    }

    $this->assertSame([], $offenders, sprintf(
      "%d physical offset(s) in components that have no RTL rules. Use the "
      . "logical property (margin-inline-start, padding-inline-end, "
      . "inset-inline-start) so the component mirrors:\n  - %s",
      count($offenders),
      implode("\n  - ", $offenders)
    ));
  }

  /**
   * The tokens must have exactly one home.
   */
  public function testTokensAreDefinedOnlyInTheVariablesFile(): void {
    $files = glob($this->themePath() . '/css/**/*.css') ?: [];
    $files = array_merge($files, glob($this->themePath() . '/css/*.css') ?: []);
    $offenders = [];
    foreach ($files as $file) {
      if (basename($file) === 'solo-variables.css') {
        continue;
      }
      $body = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));
      if (preg_match('/--solo-text-align-(?:start|end)\s*:/', $body)) {
        $offenders[] = str_replace($this->themePath() . '/', '', $file);
      }
    }
    $this->assertSame(
      [],
      $offenders,
      'The direction tokens must be defined only in css/base/solo-variables.css.'
    );
  }

}
