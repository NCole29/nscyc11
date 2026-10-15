<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a colour scheme cannot out-rank a region's own colour setting.
 *
 * A region's colour settings are emitted as inline custom properties on the
 * region wrapper (style="--r-br:#hex;..."). Inline style beats any stylesheet
 * rule on the wrapper itself, and descendants inherit from it, so the setting
 * reaches the region's content as long as nothing re-declares the variable on
 * the descendants.
 *
 * Fourteen colour schemes did exactly that for --r-br, with selectors such as
 * ".page-wrapper .main-container *". A rule on "*" matches every descendant
 * directly, and a directly matched declaration always beats an inherited
 * value, so a region's "borders" setting coloured only the wrapper's own
 * border and nothing inside it. Measured on sts.local with
 * solo-midnight-whispers: r_br failed in 14 of 30 regions; every other
 * variable passed.
 *
 * The "*" was not a mistake to delete: it pins the scheme colour on every
 * descendant regardless of anything in between. The fix keeps it and only
 * skips descendants of an element whose inline style sets the variable, via
 * :where(:not([style*="--r-br:"] *)). :where() keeps the specificity of the
 * old "*", so the scheme's other --r-br rules (form fields, buttons, menus)
 * win or lose exactly as before.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class SchemeRegionVariableOverrideTest extends TestCase {

  /**
   * The theme root.
   */
  protected function themePath(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Splits a stylesheet into [selector list, declaration block] pairs.
   *
   * @return array<int, array{0: string, 1: string}>
   *   One entry per rule.
   */
  protected function rules(string $path): array {
    $raw = file_get_contents($path);
    $this->assertIsString($raw);
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $raw);
    preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css, $m, PREG_SET_ORDER);
    return array_map(fn(array $r): array => [trim($r[1]), $r[2]], $m);
  }

  /**
   * No scheme may declare a region variable on a bare descendant "*".
   */
  public function testSchemesDoNotPinRegionVariablesOnEveryDescendant(): void {
    $files = glob($this->themePath() . '/css/predefined/*.css') ?: [];
    $this->assertGreaterThan(40, count($files), 'The colour schemes were not found.');

    $offenders = [];
    $guarded = 0;
    foreach ($files as $file) {
      foreach ($this->rules($file) as [$selectors, $body]) {
        if (!preg_match_all('/(--r-[a-z-]+)\s*:/', $body, $vars)) {
          continue;
        }
        // Split on top-level commas only: :not(a, b) is one selector.
        $list = preg_split('/,(?![^()]*\))/', $selectors) ?: [];
        foreach (array_map('trim', $list) as $selector) {
          $selector = (string) preg_replace('/\s+/', ' ', $selector);
          foreach (array_unique($vars[1]) as $var) {
            $guard = ':where(:not([style*="' . $var . ':"] *))';
            if (str_ends_with($selector, ' ' . $guard)) {
              $guarded++;
              continue;
            }
            if (preg_match('/\s\*$/', $selector)) {
              $offenders[] = sprintf('%s: "%s" declares %s', basename($file), $selector, $var);
            }
          }
        }
      }
    }
    $this->assertSame([], $offenders,
      'A scheme rule on a bare descendant "*" re-declares a region variable on '
      . 'every element, so the region\'s own setting (inline on the wrapper) '
      . 'only colours the wrapper. Use "<container> :where(:not([style*="'
      . '--r-xx:"] *))" instead.');
    // The guard must actually be in use; a regex that matches nothing would
    // pass the assertion above on any input.
    $this->assertGreaterThanOrEqual(50, $guarded, 'Expected the 50 guarded scheme selectors.');
  }

  /**
   * Every scheme --r-br rule yields to a region's own "borders" setting.
   *
   * All 50 schemes also set --r-br directly on form fields, buttons and menu
   * items (".page-wrapper .solo-input" and friends). A rule that matches the
   * element itself beats the value it would inherit from the region wrapper,
   * so a region's borders setting never reached a real Drupal form field.
   * Measured on sts.local: the login form's username field in Content kept
   * the scheme's #a2a2a2 while the region set #8b69c3.
   *
   * Each selector must end in the guard, except ".page-wrapper" (the root,
   * never under a region) and hover/focus/active states, which keep the
   * scheme's interaction colour on purpose: a region has no hover-border
   * setting, and dropping them would remove the visible state change.
   *
   * A guarded rule must declare nothing but --r-br, or the guard would also
   * hand that region's other colours back to inheritance.
   */
  public function testSchemeBorderRulesYieldToRegionSetting(): void {
    $files = glob($this->themePath() . '/css/predefined/*.css') ?: [];
    $this->assertGreaterThan(40, count($files), 'The colour schemes were not found.');
    $guard = ':where(:not([style*="--r-br:"] *))';

    $unguarded = [];
    $overreach = [];
    $guarded = 0;
    foreach ($files as $file) {
      foreach ($this->rules($file) as [$selectors, $body]) {
        if (!preg_match('/--r-br\s*:/', $body)) {
          continue;
        }
        $list = preg_split('/,(?![^()]*\))/', $selectors) ?: [];
        $rule_guarded = FALSE;
        foreach ($list as $selector) {
          $selector = trim((string) preg_replace('/\s+/', ' ', $selector));
          if (str_ends_with($selector, $guard)) {
            $guarded++;
            $rule_guarded = TRUE;
            continue;
          }
          if ($selector === '.page-wrapper' || preg_match('/:(hover|focus|focus-within|focus-visible|active)\b/', $selector)) {
            continue;
          }
          $unguarded[] = sprintf('%s: "%s"', basename($file), $selector);
        }
        if ($rule_guarded && preg_match_all('/(--?[a-z][\w-]*)\s*:/', $body, $props) && array_diff($props[1], ['--r-br'])) {
          $overreach[] = sprintf('%s: "%s"', basename($file), substr($selectors, 0, 80));
        }
      }
    }
    $this->assertSame([], $unguarded,
      'These scheme selectors set --r-br without the guard, so they beat a '
      . 'region\'s own borders setting. Append ' . $guard . '.');
    $this->assertSame([], $overreach, 'A guarded rule must declare only --r-br.');
    $this->assertGreaterThanOrEqual(900, $guarded, 'Expected the guarded scheme border selectors.');
  }

  /**
   * The guard matches the inline style text the theme actually emits.
   *
   * The CSS guard is a substring match on "--r-br:" (no space). That is only
   * correct while _get_region_attributes_for_region() joins name and value
   * with a bare colon, so this pins the two together.
   */
  public function testInlineStyleFormatMatchesTheGuard(): void {
    $source = file_get_contents($this->themePath() . '/includes/_solo_theme_preprocess.inc');
    $this->assertIsString($source);
    $this->assertStringContainsString(
      '$css_var = "{$css_var}:{$css_val}";',
      $source,
      'The region inline style format changed; the [style*="--r-xx:"] guard in '
      . 'css/predefined/*.css must change with it.'
    );
    $this->assertStringContainsString("'__r_br'", $source, 'The borders region variable was renamed.');
  }

}
