<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Every procedural hook in includes/ must have an attribute twin in src/Hook.
 *
 * WHY THIS EXISTS
 * Drupal 12 builds a theme's hook list by scanning exactly two places: the
 * <theme>.theme file and the classes under src/Hook. Solo's procedural hooks
 * live in includes/*.inc, which that scan never opens, so on Drupal 12 a hook
 * there runs ONLY if a #[Hook] method in src/Hook implements it. Drupal 11
 * still finds the procedural function, so the theme works there and nothing
 * looks wrong until it runs on 12.
 *
 * Measured 2026-10-05 on Drupal 12.0.0-beta1: solo_preprocess_breadcrumb and
 * solo_preprocess_views_view_table had no twin and never ran -- the breadcrumb
 * lost the page title Solo appends, and sortable table headings lost their
 * class. The Drupal.org bot's conversion missed both as well, and the existing
 * hook-registration test stayed green throughout.
 *
 * THE RULE THIS ENFORCES, in the bot's shape: a hook-shaped solo_* function in
 * includes/ carries #[LegacyHook] and has a method carrying #[Hook('<hook>')]
 * in src/Hook. #[LegacyHook] keeps Drupal 10 and pre-attribute Drupal 11
 * calling the procedural function, while newer core skips it and calls the
 * class, so the hook runs exactly once on every supported major.
 *
 * Preprocess functions for a theme hook SUGGESTION (a name containing "__",
 * e.g. solo_preprocess_menu__account) are covered too. They used to be
 * excluded because Drupal 12 still found them through the registry's
 * defined-functions scan, which depends on solo.theme being loaded; since
 * solo.theme declares #[ExtensionFileIsConverted], Solo must work without it
 * on Drupal >= 11.3, so they are now #[Hook] methods like every other hook.
 *
 * Reads the files as tokens rather than booting Drupal, so it runs in
 * milliseconds and on every core version alike.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class HookConversionDriftTest extends TestCase {

  /**
   * Procedural solo_* functions that are callbacks or helpers, not hooks.
   *
   * Each is referenced by name from code rather than discovered by core.
   */
  private const NOT_HOOKS = [
    'solo_apply_hidden_content_types',
    'solo_theme_settings_validate',
    'solo_settings_search_submit',
    'solo_utilities__check_solo_theme_hierarchy',
    'solo_utilities__check_solo_theme_hierarchy_active',
  ];

  /**
   * The theme root.
   */
  private function themePath(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Lists top-level functions in a file with whether #[LegacyHook] precedes.
   *
   * @return array<string, bool>
   *   TRUE when the function carries #[LegacyHook], keyed by function name.
   */
  private function proceduralFunctions(string $file): array {
    $tokens = token_get_all((string) file_get_contents($file));
    $found = [];
    $pending_attributes = '';
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
      $token = $tokens[$i];
      if (is_array($token) && $token[0] === T_ATTRIBUTE) {
        // Collect the attribute text up to its closing bracket.
        $depth = 1;
        $text = '#[';
        for ($i++; $i < $count && $depth > 0; $i++) {
          $part = is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
          $depth += ($part === '[') - ($part === ']');
          $text .= $part;
        }
        $i--;
        $pending_attributes .= $text;
        continue;
      }
      if (is_array($token) && $token[0] === T_FUNCTION) {
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
          $j++;
        }
        if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
          $found[$tokens[$j][1]] = str_contains($pending_attributes, 'LegacyHook');
        }
        $pending_attributes = '';
        continue;
      }
      if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) {
        $pending_attributes = '';
      }
    }
    return $found;
  }

  /**
   * Lists every hook named by a #[Hook('...')] attribute under src/Hook.
   *
   * @return array<string, string>
   *   The file that implements it, keyed by hook name.
   */
  private function attributeHooks(): array {
    $hooks = [];
    foreach (glob($this->themePath() . '/src/Hook/*.php') ?: [] as $file) {
      preg_match_all("/#\\[Hook\\('([a-z0-9_]+)'/", (string) file_get_contents($file), $matches);
      foreach ($matches[1] as $hook) {
        $hooks[$hook] = basename($file);
      }
    }
    return $hooks;
  }

  /**
   * The procedural files core does not scan on Drupal 12.
   *
   * @return list<string>
   *   Absolute paths.
   */
  private function includeFiles(): array {
    return glob($this->themePath() . '/includes/*.inc') ?: [];
  }

  /**
   * Every hook-shaped procedural function has a #[Hook] twin and LegacyHook.
   */
  public function testEveryProceduralHookHasAnAttributeTwin(): void {
    $attribute_hooks = $this->attributeHooks();
    $checked = 0;
    $missing = [];
    foreach ($this->includeFiles() as $file) {
      foreach ($this->proceduralFunctions($file) as $function => $has_legacy_hook) {
        if (!str_starts_with($function, 'solo_') || in_array($function, self::NOT_HOOKS, TRUE)) {
          continue;
        }
        $hook = substr($function, strlen('solo_'));
        $checked++;
        if (!isset($attribute_hooks[$hook])) {
          $missing[] = sprintf("%s (%s): no #[Hook('%s')] in src/Hook -- dead on Drupal 12", $function, basename($file), $hook);
        }
        if (!$has_legacy_hook) {
          $missing[] = sprintf('%s (%s): no #[LegacyHook] -- would run twice on core that reads both', $function, basename($file));
        }
      }
    }

    // A scan that finds nothing proves nothing. Solo had 36 such hooks when
    // this was written; a broken tokenizer or a moved directory must go red
    // rather than pass on an empty set.
    $this->assertGreaterThanOrEqual(30, $checked, sprintf('Scanned %d procedural hooks; expected at least 30.', $checked));
    $this->assertSame([], $missing, "Procedural hooks that do not run on Drupal 12:\n" . implode("\n", $missing));
  }

  /**
   * Every listed non-hook must still exist, or the exclusion list has rotted.
   */
  public function testNonHookListIsCurrent(): void {
    $all = [];
    foreach ($this->includeFiles() as $file) {
      $all += $this->proceduralFunctions($file);
    }
    foreach (self::NOT_HOOKS as $function) {
      $this->assertArrayHasKey($function, $all, sprintf('%s is listed as a non-hook but no longer exists; remove it from NOT_HOOKS.', $function));
    }
  }

  /**
   * The array guards of #3609300 must survive in the hook classes.
   *
   * Conversion 0d81bee moved the preprocess bodies into src/Hook and dropped
   * the guards shipped in 1.0.35, so an array id or #name (filebrowser) went
   * back to a TypeError (#3630728). Behaviour is covered by
   * ArrayAttributeGuardTest; this fails if the guard text itself disappears.
   */
  public function testArrayGuardsSurviveConversion(): void {
    $expected = [
      '/src/Hook/SoloThemePreprocessHooks.php' => [
        'str_contains($container_id' => '$container_id',
        'stripos($element_name' => '$element_name',
      ],
      '/includes/_solo_theme_preprocess.inc' => [
        'str_contains($child_name' => '$child_name',
      ],
    ];
    $checked = 0;
    foreach ($expected as $path => $anchors) {
      $source = (string) file_get_contents($this->themePath() . $path);
      foreach ($anchors as $anchor => $variable) {
        $start = strpos($source, $anchor);
        $this->assertNotFalse($start, sprintf('%s: anchor %s not found.', $path, $anchor));
        // The guard must sit in the lines just before the first use.
        $window = substr($source, max(0, $start - 700), 700);
        $this->assertStringContainsString(sprintf('is_array(%s)', $variable), $window, sprintf('%s: no is_array() guard on %s.', $path, $variable));
        $this->assertStringContainsString(sprintf('is_string(%s)', $variable), $window, sprintf('%s: no is_string() guard on %s.', $path, $variable));
        $checked++;
      }
    }
    $this->assertSame(3, $checked);
  }

}
