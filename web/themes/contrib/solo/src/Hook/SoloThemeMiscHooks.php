<?php

namespace Drupal\solo\Hook;

use Drupal\Core\Url;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\solo\SoloIncludes;

/**
 * Hook implementations for solo.
 */
class SoloThemeMiscHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    SoloIncludes::load();
    _load_google_fonts($attachments);
    // NEW: Use single parameterized function instead of three separate ones.
    _load_external_google_font($attachments, 'site_font_google_special_on');
    _load_external_google_font($attachments, 'site_name_google_font');
    _load_external_google_font($attachments, 'site_pagetitle_google_font');
    // Preloader text font is attached only when preloader is shown (see _solo_preprocess_html_preloader) to avoid blocking first paint when preloader is disabled or uses spinner.
    _load_predefined_color_theme($attachments);
    _load_font_awesome($attachments);
    _load_full_opacity($attachments);
    _site_load_w3css_library($attachments);
    _load_color_coded_system_tabs($attachments);
    // Check if debug mode is enabled in theme settings.
    if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('solo_menu_debug'), fn() => theme_get_setting('solo_menu_debug'))) {
      // NEW: Get verbosity setting.
      $verbosity = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('solo_menu_debug_verbosity'), fn() => theme_get_setting('solo_menu_debug_verbosity')) ?: 'normal';
      // Enable debug mode in drupalSettings.
      $attachments['#attached']['drupalSettings']['solo']['debug'] = TRUE;
      // NEW: Pass verbosity to JavaScript.
      $attachments['#attached']['drupalSettings']['solo']['debug_verbosity'] = $verbosity;
      // Attach the debug library.
      $attachments['#attached']['library'][] = 'solo/solo-debug';
      // Display warning message on every page (ENHANCED - optional)
      \Drupal::messenger()->addWarning($this->t('<strong>SOLO DEBUG MODE ACTIVE</strong> (Verbosity: @verbosity) — Development tools are enabled. Open browser console and run showReport() for menu diagnostics. <a class="warning" href="@settings_url">Disable debug mode</a> before going to production.', [
            // Shows "Normal", "Minimal", or "Verbose".
        '@verbosity' => ucfirst($verbosity),
        '@settings_url' => Url::fromRoute('system.theme_settings_theme', [
          'theme' => \Drupal::theme()->getActiveTheme()->getName(),
        ])->toString(),
      ]));
    }
  }

  /**
   * Implements hook_modules_disabled().
   */
  #[Hook('modules_disabled')]
  public static function modulesDisabled(array $modules): void {
    SoloIncludes::load();
    if (in_array('solo_utilities', $modules, TRUE)) {
      // Detect all installed themes that are using Solo as a base or are Solo.
      $theme_handler = \Drupal::service('theme_handler');
      $theme_names = array_keys($theme_handler->listInfo());
      foreach ($theme_names as $theme_name) {
        $theme = $theme_handler->getTheme($theme_name);
        if (!$theme) {
          continue;
        }
        // Skip if it's not Solo or a sub-theme of Solo.
        $base_themes = array_keys($theme->info['base theme'] ?? []);
        if ($theme_name !== 'solo' && !in_array('solo', $base_themes, TRUE)) {
          continue;
        }
        $config = \Drupal::configFactory()->getEditable("{$theme_name}.settings");
        $has_changes = FALSE;
        // Core setting keys to clear.
        $keys_to_clear = [
          'enable_custom_widths',
          'enable_custom_node_width',
          'site_widths',
        ];
        foreach ($keys_to_clear as $key) {
          if ($config->get($key) !== NULL) {
            $config->clear($key);
            $has_changes = TRUE;
          }
        }
        if ($has_changes) {
          $config->save();
          \Drupal::configFactory()->reset("{$theme_name}.settings");
        }
      }
    }
  }

}
