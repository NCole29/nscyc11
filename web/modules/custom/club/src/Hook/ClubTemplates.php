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

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  function registryAlter(&$theme_registry) {
    // Check if the focal_point theme hook exists in the registry
    if (isset($theme_registry['focal_point_preview_page'])) {
      /** @var \Drupal\Core\Extension\ExtensionPathResolver $path_resolver */
      $path_resolver = \Drupal::service('extension.path.resolver');
      
      // Get the path to your custom club module
      $custom_module_path = $path_resolver->getPath('module', 'club');
      
      // Override the path to the template folder
      $theme_registry['focal_point_preview_page']['path'] = $custom_module_path . '/templates';
      
      // Override the template filename (do not include '.html.twig' extension here)
      $theme_registry['focal_point_preview_page']['template'] = 'focal-point-preview-custom';
    }
  }

}