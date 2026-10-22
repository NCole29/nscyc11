<?php

declare(strict_types=1);

namespace Drupal\club\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Extension\ExtensionPathResolver;

class ClubTemplates {

  /** 
   * Implements hook_theme to register templates.
   */
  #[Hook('theme')]
  public function addTemplates() {
    return [
      'node__location' => [
        'template' => 'node--location',
        'base hook' => 'node',
      ],
      'field__address' => [
        'template' => 'field--address',
        'base hook' => 'field',
      ],  
      // Adding 'uri' to variables so its available in paragraph templates.
      'fontawesomeicons' => [
        'variables' => [
          'icons' => NULL,
          'layers' => FALSE,
        ],
      ],
      'fontawesomeicon' => [
        'variables' => [
          'tag' => 'i',
          'iconset' => '',
          'name' => NULL,
          'style' => NULL,
          'settings' => NULL,
          'transforms' => NULL,
          'mask' => NULL,
          'css' => NULL,
          'uri' => NULL,
        ],
      ],
    ];      
  } 

  /**
   * Implements hook_page_attachments() to add custom CSS to admin pages.
   */
  #[Hook('page_attachments')]
  function adminStyles(array &$attachments) {
    // Check if the current route is an admin route
    if (\Drupal::service('router.admin_context')->isAdminRoute()) {
      $attachments['#attached']['library'][] = 'club/admin-overrides';
    }
  }

}