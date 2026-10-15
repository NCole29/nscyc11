<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the region link colour rule out-ranks solo-normalize.css.
 *
 * Issue #3629228: from 1.0.35 the Main Container "link text" colour did
 * nothing. 64b41d0 wrapped the whole link rule in :where(), which gave it zero
 * specificity, so solo-normalize.css (`a { all: unset }`, `li a { color:
 * inherit }`) won and every link fell back to the text colour. Measured on
 * sts.local: links rendered rgb(0,0,0) with --r-lk set to #ce103d.
 *
 * The rule must keep the a:not(...) part OUTSIDE :where(), so it scores
 * (0,1,1): above normalize, and still below a paragraph's own link rules
 * (0,2,0) and up, so section link colours keep winning.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class RegionLinkColourRuleTest extends TestCase {

  /**
   * Returns [selector, body] for each rule in the regions stylesheet.
   *
   * @return array<int, array{0: string, 1: string}>
   *   One entry per rule.
   */
  protected function rules(): array {
    $raw = file_get_contents(dirname(__DIR__, 3) . '/css/base/solo-variables-regions.css');
    $this->assertIsString($raw);
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $raw);
    preg_match_all('#([^{}]+)\{([^{}]*)\}#', $css, $m, PREG_SET_ORDER);
    return array_map(fn(array $r): array => [trim((string) preg_replace('/\s+/', ' ', $r[1])), $r[2]], $m);
  }

  /**
   * The link and link-hover rules keep a:not(...) outside :where().
   */
  public function testLinkRulesOutrankNormalize(): void {
    $found = ['--r-lk,' => 0, '--r-lk-h,' => 0];
    foreach ($this->rules() as [$selector, $body]) {
      foreach (array_keys($found) as $var) {
        if (!str_contains($body, 'color: var(' . $var)) {
          continue;
        }
        $found[$var]++;
        $this->assertStringStartsWith(':where(.page-wrapper) a:not(', $selector,
          "The rule using $var must be ':where(.page-wrapper) a:not(...)'. "
          . 'Wrapping a:not(...) in :where() drops it to zero specificity and '
          . 'solo-normalize.css wins (#3629228).');
      }
    }
    $this->assertSame(1, $found['--r-lk,'], 'Expected exactly one link colour rule.');
    $this->assertSame(1, $found['--r-lk-h,'], 'Expected exactly one link hover rule.');
  }

}
