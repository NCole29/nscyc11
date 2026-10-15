<?php

/**
 * @file
 * Theme Setting.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\solo\Hook\SoloThemeMiscHooks;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Writes the dynamic CSS/JS theme settings to public:// on disk.
 *
 * Backs the "CSS Dynamic" / "JavaScript Dynamic" theme settings fields.
 * Called from the theme settings submit handler in theme-settings.php AND
 * from solo_library_info_alter() as a best-effort self-heal on library
 * discovery rebuilds. Lives in _solo_theme_misc.inc (always required from
 * solo.theme) rather than theme-settings.php (only loaded when the
 * settings form renders) so both call sites can reach it.
 *
 * Passing NULL for one of the arguments means "leave that file alone on
 * this call" — used by the self-heal path in solo_library_info_alter()
 * when only one of the two files is drifted.
 *
 * Passing an empty string means "the user cleared the field, delete the
 * file" — used by the submit handler when the user wipes the textarea.
 *
 * @param string|null $css_dynamic
 *   CSS source to persist. NULL to skip the CSS file entirely on this
 *   call. Empty string to delete the CSS file if it exists.
 * @param string|null $js_dynamic
 *   JS source to persist. NULL to skip the JS file entirely on this
 *   call. Empty string to delete the JS file if it exists.
 */
function _solo_write_dynamic_assets(?string $css_dynamic, ?string $js_dynamic): void {
  $file_system = \Drupal::service('file_system');
  $logger = \Drupal::logger('solo');
  $css_file_uri = 'public://solo/css/solo-css-dynamic.css';
  $js_file_uri = 'public://solo/js/solo-js-dynamic.js';

  if ($css_dynamic !== NULL) {
    $css_directory = $file_system->dirname($css_file_uri);
    if (!$file_system->prepareDirectory($css_directory, FileSystemInterface::CREATE_DIRECTORY)) {
      $logger->error('Failed to prepare directory for dynamic CSS.');
    }
    elseif ($css_dynamic !== '') {
      if ($file_system->saveData($css_dynamic, $css_file_uri, FileExists::Replace) === FALSE) {
        $logger->error('Failed to save dynamic CSS file.');
      }
    }
    else {
      try {
        $file_system->delete($css_file_uri);
      }
      catch (\Exception $e) {
        // Missing file is fine — nothing to remove.
      }
    }
  }

  if ($js_dynamic !== NULL) {
    $js_directory = $file_system->dirname($js_file_uri);
    if (!$file_system->prepareDirectory($js_directory, FileSystemInterface::CREATE_DIRECTORY)) {
      $logger->error('Failed to prepare directory for dynamic JS.');
    }
    elseif ($js_dynamic !== '') {
      if ($file_system->saveData($js_dynamic, $js_file_uri, FileExists::Replace) === FALSE) {
        $logger->error('Failed to save dynamic JS file.');
      }
    }
    else {
      try {
        $file_system->delete($js_file_uri);
      }
      catch (\Exception $e) {
        // Missing file is fine — nothing to remove.
      }
    }
  }
}

/**
 * Helper function.
 */
function _load_google_fonts(array &$attachments): void {
  $fonts = ['site_font_google_global_on', 'site_font_google_heading_on'];
  foreach ($fonts as $font) {
    if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting($font), fn() => theme_get_setting($font))) {
      $font_setting = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting($font), fn() => theme_get_setting($font));
      $font_path = 'solo/solo-gf' . (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('site_font_google_local_on'), fn() => theme_get_setting('site_font_google_local_on')) ? 'in' : 'ex') . substr($font_setting, 4);
      $attachments['#attached']['library'][] = $font_path;
    }
  }
}

/**
 * Load external Google font library for a specific setting.
 *
 * Replaces three nearly identical functions with one parameterized version.
 *
 * @param array $attachments
 *   The attachments array to modify.
 * @param string $setting_name
 *   The theme setting name to check (e.g., 'site_font_google_special_on').
 */
function _load_external_google_font(array &$attachments, string $setting_name): void {
  $font_setting = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting($setting_name), fn() => theme_get_setting($setting_name));
  if ($font_setting) {
    $library_name = 'solo/solo-gfex' . substr($font_setting, 4);
    $attachments['#attached']['library'][] = $library_name;
  }
}

/**
 * Helper function.
 */
function _load_predefined_color_theme(array &$attachments): void {
  // Default library path based on theme settings.
  $theme_category = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('theme_category'), fn() => theme_get_setting('theme_category'));
  $is_theme = '';

  if ($theme_category !== 'none') {
    $is_theme_setting = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('predefined_current_theme'), fn() => theme_get_setting('predefined_current_theme')) ?? '';
    $theme_parts = explode('|', $is_theme_setting);
    $is_theme = count($theme_parts) > 1 ? $theme_parts[1] : $theme_parts[0];
  }

  // Check if the solo_utilities module is installed.
  if (\Drupal::moduleHandler()->moduleExists('solo_utilities')) {
    // Fetch the user-selected library from solo_utilities.
    $user_library = _solo_utilities_get_user_selected_library();

    if (!empty($user_library)) {
      // Attach the user-selected library if it exists.
      $attachments['#attached']['library'][] = 'solo/' . $user_library;
      // Early return if user library is set.
      return;
    }
  }

  // Fallback to the default theme library if no user-selected library.
  $theme_path = 'solo/' . $is_theme;
  $attachments['#attached']['library'][] = $theme_path;
}

/**
 * Helper function.
 */
function _solo_utilities_get_user_selected_library() {
  // Ensure the solo_utilities module is installed.
  if (!\Drupal::moduleHandler()->moduleExists('solo_utilities')) {
    return NULL;
  }

  // Create an instance of LibraryLoadNegotiator and call evaluateRules.
  $color_schemes_negotiator = \Drupal::service('solo_utilities.color_schemes_negotiator');

  // Call the evaluateRules method to get the library.
  return $color_schemes_negotiator->evaluateRules();
}

/**
 * Helper function.
 */
function _load_font_awesome(array &$attachments): void {
  $fw_version = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('site_font_awesome_on'), fn() => theme_get_setting('site_font_awesome_on'));
  if ($fw_version === NULL) {
    return;
  }

  $font_awesome_versions = [
    1 => 'font-awesome-4-in',
    2 => 'font-awesome-4-ex',
    3 => 'font-awesome-5-ex',
    4 => 'font-awesome-6-ex',
  ];

  if (isset($font_awesome_versions[$fw_version])) {
    $attachments['#attached']['library'][] = 'solo/' . $font_awesome_versions[$fw_version];
  }
}

/**
 * Helper function.
 */
function _load_full_opacity(array &$attachments): void {
  // Fast path: if the feature is off, do nothing.
  if (!DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('site_opacity_onscroll_on'), fn() => theme_get_setting('site_opacity_onscroll_on'))) {
    return;
  }

  /** @var \Drupal\Core\Routing\AdminContext $admin_context */
  $admin_context = \Drupal::service('router.admin_context');
  if ($admin_context->isAdminRoute()) {
    return;
  }

  // Attach library when on a non-admin route.
  $attachments['#attached']['library'][] = 'solo/solo-full-opacity';

  // Optional: Provide sane defaults only if not set already.
  // This avoids clobbering prior attachments (e.g., from preprocess).
  $settings =& $attachments['#attached']['drupalSettings']['solo']['fullOpacity'];
  if (!isset($settings)) {
    $settings = [
      'animationHeightRatio' => 0.15,
      'fadeInDuration' => '1s',
      'fadeOutDuration' => '0.5s',
      'throttleDelay' => 16,
      'debounceDelay' => 250,
      'useIntersectionObserver' => TRUE,
    ];
  }
}

/**
 * Helper function.
 */
function _site_load_w3css_library(array &$attachments): void {
  if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('site_load_w3css_on'), fn() => theme_get_setting('site_load_w3css_on'))) {
    $attachments['#attached']['library'][] = 'solo/w3-css';
  }
}

/**
 * Helper function.
 */
function _load_color_coded_system_tabs(array &$attachments): void {
  if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('color_coded_system_tabs'), fn() => theme_get_setting('color_coded_system_tabs'))) {
    $attachments['#attached']['library'][] = 'solo/solo-color-coded-system-tabs';
  }
}

/**
 * Implements hook_page_attachments_alter().
 */
#[LegacyHook]
function solo_page_attachments_alter(array &$attachments): void {
  \Drupal::classResolver(SoloThemeMiscHooks::class)->pageAttachmentsAlter($attachments);
}

/**
 * Helper function.
 */
function _solo_convert_hex_to_hsl(string $hex_string): array {
  // Convert hexcode pairs to rgb values (0-255).
  $hex_val = trim($hex_string, '#');
  $r0 = hexdec($hex_val[0] . $hex_val[1]);
  $g0 = hexdec($hex_val[2] . $hex_val[3]);
  $b0 = hexdec($hex_val[4] . $hex_val[5]);

  // Convert rgb's 0-255 to decimal values.
  $r = fdiv($r0, 255);
  $g = fdiv($g0, 255);
  $b = fdiv($b0, 255);

  // Calculate Hue.
  $c_min = min($r, $g, $b);
  $c_max = max($r, $g, $b);
  $delta = $c_max - $c_min;

  if ($delta == 0) {
    $h = 0;
  }
  else {
    switch ($c_max) {
      case $r:
        $h = fmod((($g - $b) / $delta), 6);
        break;

      case $g:
        $h = (($b - $r) / $delta) + 2;
        break;

      case $b:
        $h = (($r - $g) / $delta) + 4;
        break;

      default:
        $h = 0;
        break;
    }
  }

  $h = round($h * 60);

  // Shift hue range from [-60 - 300] to [0 - 360].
  if ($h < 0) {
    $h += 360;
  }

  // Calculate Lightness.
  $l = ($c_max + $c_min) / 2;

  // Calculate Saturation.
  $s = $delta == 0 ? 0 : $delta / (1 - abs((2 * $l) - 1));

  // Convert Saturation and Lightness to percentages.
  return [round($h), round($s * 100) . '%', round($l * 100) . '%'];
}

/**
 * Implements hook_modules_disabled().
 */
#[LegacyHook]
function solo_modules_disabled(array $modules): void {
  \Drupal::classResolver(SoloThemeMiscHooks::class)->modulesDisabled($modules);
}
