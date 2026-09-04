<?php

declare(strict_types=1);

namespace Drupal\club\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeManagerInterface;

class ClubStyles {
  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function attachStyles(array &$attachments) {
    $attachments['#attached']['library'][] = 'club/club_styles';
   
    // Get the name of the active theme.
    $activeTheme = \Drupal::service('theme.manager')->getActiveTheme()->getName();
    
    switch ($activeTheme) {
      case('olivero'): 
        $attachments['#attached']['library'][] = 'club/olivero_calendar';
        break;
      case('club_solo'): 
        $attachments['#attached']['library'][] = 'club/solo_calendar';
        break;
    }

  }
}