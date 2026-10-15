<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Theme code must never fetch its own classes from the service container.
 *
 * WHY THIS EXISTS
 * Solo 1.0.35 fatals on every page on Drupal 10 and on Drupal 11.0-11.2:
 * "You have requested a non-existent service
 * Drupal\solo\Hook\SoloThemeMiscHooks" (issue #3628961). Each #[LegacyHook]
 * wrapper in includes/ called \Drupal::service(SoloTheme...Hooks::class), and
 * that service exists only when core registers it:
 *
 * - DrupalKernel::discoverServiceProviders() reads <name>.services.yml for
 *   MODULES only, on every core version, so solo.services.yml was never
 *   loaded anywhere.
 * - ThemeHookCollectorPass, added in Drupal 11.3.0, registers a theme's Hook
 *   classes as autowired services. Below 11.3 nothing does.
 *
 * So the wrappers worked on 11.3+ and 12, where every test ran, and broke on
 * every older core the theme declares support for. The wrappers now use
 * \Drupal::classResolver(), which returns the registered service on 11.3+ and
 * instantiates the class itself below that.
 *
 * WHAT THIS ENFORCES
 * 1. No \Drupal::service() call in theme code names a class in the theme's
 *    own namespace (Drupal\solo\... or Drupal\solo_subtheme\...). Core
 *    services such as ThemeSettingsProvider are not flagged: `use` statements
 *    are resolved, so the check is about the namespace, not the name.
 * 2. No *.services.yml ships in a theme directory, because core never reads
 *    one and its presence is what made the container lookup look safe.
 *
 * Red-proofed against the 1.0.35 tree: 39 calls flagged, and both
 * services.yml files. Reads files as text, so it runs on every core version,
 * including the Drupal 10 job where the fatal actually happens.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class HookClassResolutionTest extends TestCase {

  /**
   * The theme root.
   */
  private function themePath(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Procedural theme files: the .theme files and everything in includes/.
   *
   * @return list<string>
   *   Absolute paths.
   */
  private function proceduralFiles(): array {
    $root = $this->themePath();
    return array_merge(
      glob($root . '/*.theme') ?: [],
      glob($root . '/includes/*.inc') ?: [],
      glob($root . '/solo_subtheme/*.theme') ?: [],
    );
  }

  /**
   * Maps each imported short class name to its fully qualified name.
   *
   * @return array<string, string>
   *   FQCN keyed by the short name (or alias) used in the file.
   */
  private function imports(string $code): array {
    $map = [];
    preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $fqcn = ltrim($match[1], '\\');
      $short = $match[2] ?? '';
      if ($short === '') {
        $parts = explode('\\', $fqcn);
        $short = end($parts);
      }
      $map[$short] = $fqcn;
    }
    return $map;
  }

  /**
   * No wrapper asks the container for a class the theme itself defines.
   */
  public function testNoContainerLookupOfThemeOwnClasses(): void {
    $offenders = [];
    $resolved = 0;
    foreach ($this->proceduralFiles() as $file) {
      $code = (string) file_get_contents($file);
      $imports = $this->imports($code);
      preg_match_all('/\\\\Drupal::(service|classResolver)\(\s*\\\\?([\w\\\\]+)::class\s*\)/', $code, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
      foreach ($calls as $call) {
        $name = $call[2][0];
        $fqcn = str_contains($name, '\\') ? ltrim($name, '\\') : ($imports[$name] ?? $name);
        if (!preg_match('/^Drupal\\\\solo(_subtheme)?\\\\/', $fqcn)) {
          continue;
        }
        if ($call[1][0] === 'classResolver') {
          $resolved++;
          continue;
        }
        $line = substr_count(substr($code, 0, $call[0][1]), "\n") + 1;
        $offenders[] = sprintf('%s:%d \\Drupal::service(%s::class)', basename($file), $line, $name);
      }
    }

    $this->assertSame([], $offenders, sprintf(
      "Theme classes are not container services below Drupal 11.3; use \\Drupal::classResolver() instead. %d offending call(s):\n%s",
      count($offenders),
      implode("\n", $offenders)
    ));
    // A scan that matches nothing proves nothing: Solo has 39 wrappers that
    // resolve a Hook class. A moved directory or a broken pattern must go red.
    $this->assertGreaterThanOrEqual(30, $resolved, sprintf('Found %d classResolver() wrappers; expected at least 30.', $resolved));
  }

  /**
   * No theme directory ships a services.yml, which core never loads.
   */
  public function testNoThemeServicesYml(): void {
    $root = $this->themePath();
    $found = array_merge(
      glob($root . '/*.services.yml') ?: [],
      glob($root . '/solo_subtheme/*.services.yml') ?: [],
    );
    $this->assertSame([], array_map('basename', $found), 'Drupal core reads services.yml for modules only; a theme services.yml is never loaded.');
  }

}
