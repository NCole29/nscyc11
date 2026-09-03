<?php

declare(strict_types=1);

namespace Drupal\bikeclub\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Theme\ThemeManagerInterface;

class BikeclubStyles {
  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function attachStyles(array &$attachments) {
    $attachments['#attached']['library'][] = 'bikeclub/bikeclub_styles';
   
    // Get the name of the active theme.
    $activeTheme = \Drupal::service('theme.manager')->getActiveTheme()->getName();
    
    switch ($activeTheme) {
      case('olivero'): 
        $attachments['#attached']['library'][] = 'bikeclub/olivero_calendar';
        break;
      case('bikeclub_solo'): 
        $attachments['#attached']['library'][] = 'bikeclub/solo_calendar';
        break;
    }

  }
}