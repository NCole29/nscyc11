<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use Drupal\solo\SoloIncludes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Solo.theme loads its include files itself and needs no Solo class.
 *
 * WHY THIS EXISTS
 * Solo 1.0.37 replaced the require_once lines in solo.theme with a call to
 * \Drupal\solo\SoloIncludes::load(). Core includes solo.theme before the
 * theme's src/ namespace is registered on some requests: the CSS/JS aggregate
 * route initializes the theme from the cached ActiveTheme and never calls
 * ThemeHandler::listInfo(), which is what registers it. Every aggregate
 * request then failed with 'Class "Drupal\solo\SoloIncludes" not found', and
 * every page lost its CSS and JS
 * (https://www.drupal.org/project/solo/issues/3628961). Measured on Drupal
 * 10.6.19: HTTP 500 on the aggregate URL with 1.0.37's solo.theme, HTTP 200
 * with this one.
 *
 * WHAT THIS ENFORCES
 * 1. solo.theme names no Drupal\solo\ class, so including it needs nothing
 *    from the class loader.
 * 2. solo.theme require_once's exactly the files in SoloIncludes::FILES, in
 *    the same order, so the two lists cannot drift.
 *
 * Both read solo.theme as tokens rather than booting Drupal.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class ThemeFileIncludesTest extends TestCase {

  /**
   * Returns solo.theme's tokens with whitespace and comments removed.
   *
   * @return list<array{0: int|null, 1: string, 2: int}>
   *   Each token as [token id or NULL, text, line].
   */
  private function themeFileTokens(): array {
    $result = [];
    $line = 1;
    foreach (token_get_all((string) file_get_contents(dirname(__DIR__, 3) . '/solo.theme')) as $token) {
      if (is_array($token)) {
        $line = $token[2];
        if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) {
          continue;
        }
        $result[] = [$token[0], $token[1], $token[2]];
      }
      else {
        $result[] = [NULL, $token, $line];
      }
    }
    return $result;
  }

  /**
   * Requirement 1: solo.theme names no class from the Solo namespace.
   */
  public function testThemeFileNamesNoSoloClass(): void {
    $names = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
    $found = [];
    foreach ($this->themeFileTokens() as $token) {
      if (in_array($token[0], $names, TRUE) && preg_match('/^\\\\?Drupal\\\\solo(\\\\|$)/i', $token[1])) {
        $found[] = sprintf('line %d: %s', $token[2], $token[1]);
      }
    }
    $this->assertSame([], $found, "solo.theme names a Drupal\\solo\\ class. Core can include solo.theme before that namespace is registered (the CSS/JS aggregate route), so this is a fatal there:\n" . implode("\n", $found));
  }

  /**
   * Requirement 2: solo.theme loads exactly SoloIncludes::FILES, in order.
   */
  public function testThemeFileLoadsSoloIncludesFiles(): void {
    $tokens = $this->themeFileTokens();
    $loaded = [];
    foreach ($tokens as $i => $token) {
      if (!in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], TRUE)) {
        continue;
      }
      // The expected shape: require_once __DIR__ . '/includes/<file>';.
      $statement = array_slice($tokens, $i, 5);
      $this->assertSame('require_once', strtolower($token[1]), sprintf('solo.theme line %d must use require_once.', $token[2]));
      $this->assertSame([T_DIR, '.', T_CONSTANT_ENCAPSED_STRING, ';'], [
        $statement[1][0] ?? NULL,
        $statement[2][1] ?? NULL,
        $statement[3][0] ?? NULL,
        $statement[4][1] ?? NULL,
      ], sprintf("solo.theme line %d must read: require_once __DIR__ . '/includes/<file>';", $token[2]));
      $path = trim($statement[3][1], '\'"');
      $this->assertStringStartsWith('/includes/', $path, sprintf('solo.theme line %d loads a file outside includes/.', $token[2]));
      $loaded[] = substr($path, strlen('/includes/'));
    }
    $this->assertSame(SoloIncludes::FILES, $loaded, 'The require_once list in solo.theme must equal SoloIncludes::FILES, in the same order.');
  }

}
