<?php

declare(strict_types=1);

namespace Drupal\solo;

use Drupal\Core\Form\FormStateInterface;

/**
 * Loads Solo's procedural include files: the one list of them.
 *
 * WHY THIS EXISTS
 * Drupal 11.5.0 deprecated <theme>.theme files and Drupal 13 stops loading
 * them. Solo's src/Hook classes call helper functions that live in the include
 * files below, which used to be pulled in only by solo.theme. Every #[Hook]
 * method therefore calls ::load() first, so the hooks work on Drupal >= 11.3
 * whether or not core still includes solo.theme. solo.theme calls ::load()
 * too, so Drupal 10 and Drupal 11 before 11.3 -- which run the #[LegacyHook]
 * procedural functions defined in these files -- load exactly what they loaded
 * before.
 *
 * Every include holds only `use` statements and function declarations, so
 * loading it from inside a method defines the same global functions as
 * loading it at file scope.
 *
 * tests/src/Unit/ThemeFileIndependenceTest.php enforces this list against the
 * helpers the Hook classes call, and against the files on disk.
 */
final class SoloIncludes {

  /**
   * The include files, relative to includes/, in their original load order.
   */
  public const FILES = [
    '_theme_settings_helper_functions.inc',
    '_solo_preloader.inc',
    '_solo_theme_preprocess.inc',
    '_solo_theme_suggestions.inc',
    '_solo_theme_alter.inc',
    '_solo_theme_misc.inc',
    '_solo_theme_settings.inc',
  ];

  /**
   * Whether the include files have been loaded in this request.
   */
  private static bool $loaded = FALSE;

  /**
   * Loads every include file once.
   */
  public static function load(): void {
    if (self::$loaded) {
      return;
    }
    foreach (self::FILES as $file) {
      require_once dirname(__DIR__) . '/includes/' . $file;
    }
    self::$loaded = TRUE;
  }

  /**
   * Registers the include files with a form, for when it is rebuilt from cache.
   *
   * A form that names an include-defined function as a '#validate' or
   * '#submit' callback can be rebuilt from the form cache (an Ajax request, a
   * multi-step rebuild) without any Solo hook running first in that request.
   * FormCache::loadCachedFormState() then loads only the files listed in
   * $form_state's build info 'files'. Core's theme settings form lists
   * solo.theme there only while the file exists (ThemeSettingsForm), so
   * without solo.theme the callback would be an undefined function. This lists
   * every SoloIncludes::FILES entry -- the callbacks call helpers across the
   * include files -- as a path relative to the Drupal root, the form core
   * itself uses, and adds each path at most once.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form that names an include-defined callback.
   */
  public static function addToFormBuildInfo(FormStateInterface $form_state): void {
    $theme_path = \Drupal::service('extension.path.resolver')->getPath('theme', 'solo');
    $files = $form_state->getBuildInfo()['files'] ?? [];
    foreach (self::FILES as $file) {
      $path = $theme_path . '/includes/' . $file;
      if (!in_array($path, $files, TRUE)) {
        $files[] = $path;
      }
    }
    $form_state->addBuildInfo('files', $files);
  }

}
