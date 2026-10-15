<?php

namespace Drupal\solo_subtheme\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for solo_subtheme.
 */
class SoloSubthemeHooks {
  /**
   * @file
   * Add your custom theme override functions here.
   */

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_block_alter')]
  public static function themeSuggestionsBlockAlter(&$suggestions, $variables) {
    // Load theme suggestions for blocks from parent theme.
    foreach ($suggestions as &$suggestion) {
      $suggestion = str_replace('solo_', 'solo_subtheme_', $suggestion);
    }
  }

}
