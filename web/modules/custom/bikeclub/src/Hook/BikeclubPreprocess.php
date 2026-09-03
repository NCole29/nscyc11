<?php

declare(strict_types=1);

namespace Drupal\bikeclub\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element\FormElement;

class BikeclubPreprocess {
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

}