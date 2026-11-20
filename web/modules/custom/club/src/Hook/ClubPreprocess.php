<?php

declare(strict_types=1);

namespace Drupal\club\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Markup;

class ClubPreprocess {
  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_field')]
  public function PreprocessFields(&$vars) {
    // Hide country name everywhere address is displayed.
    if ($vars['field_name'] == 'field_address') {
      $vars['items'][0]['content']['country']['#value'] = '';
    }

    // Hide field_lunch_same if "Other, enter below"
    if (($vars['field_name'] == 'field_lunch_same') && 
         $vars['items'][0]['content']['#markup'] == 'Other, enter below') {
        
        $vars['label_hidden'] = TRUE;
        $vars['items'][0]['content']['#markup']= '';
    }
  }  

  /**
   * Implements hook_preprocess_status_messages().
   */
  #[Hook('preprocess_status_messages')]
  function club_preprocess_status_messages(array &$variables): void {
    if (empty($variables['message_list']['error'])) {
      return;
    }

    foreach ($variables['message_list']['error'] as $delta => $message) {
      $text = (string) $message;

      // Match the exact string or a distinctive substring.
      if (str_contains($text, 'One or dates reference a missing recurring rule')) {
        $variables['message_list']['error'][$delta] = t('One or more recurring rides are missing a recurring date rule.');
      }
    }
  }

}