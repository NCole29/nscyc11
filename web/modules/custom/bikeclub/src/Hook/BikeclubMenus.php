<?php

declare(strict_types=1);

namespace Drupal\bikeclub\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Link;

/**
 * Hook implementations for menus and links.
 */
class BikeclubMenus {

  /**
   * Constructor for BikeclubNode Hooks.
   */
  public function __construct(
    protected Connection $connection,
    protected CurrentPathStack $currentPath,
    protected AccountProxyInterface $currentUser,
    protected MessengerInterface $messenger,
    protected RouteMatchInterface $routeMatch
  ) {
  }

  /**
   * Implements hook_link_alter().
   */
  #[Hook('link_alter')]
  public function bikeclub_link_alter(&$variables) {    
    // Change 'Home' to fontawesome icon.   
    if (isset($variables['url']) & $variables['url']->isRouted()) {
      if ($variables['url']->getRouteName() === '<front>') {
        $variables['options']['attributes']['class'] = 'home-button';
        $variables['text'] = t('<i class="fa fa-home fa-lg" aria-hidden="true"></i>');
      }
    }
  }   

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function bikeclub_local_tasks_alter(&$local_tasks) {
    // Hide tabs above Login form
    //$this->messenger->addStatus('local_tasks_alter: ' . $route_name);
    unset($local_tasks["user.pass"]);
  }

  /**
   * Implements hook_menu_local_actions_alter().
   */
  #[Hook('menu_local_actions_alter')]
  public function bikeclub_menu_local_actions_alter(&$local_actions) {

    // People > List: remove 'Add user' button (users are managed through memberships)
    unset($local_actions["user_admin_create"]);
    // People > Event participants: remove 'Add participant' button
    unset($local_actions["civicrm.civicrm_participant_add"]);
    // People > Contacts: remove 'New contact' button
    unset($local_actions["civicrm.civicrm_contact_add"]);

  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function bikeclub_menu_local_tasks_alter(&$data, $route_name, 
     \Drupal\Core\Cache\RefinableCacheableDependencyInterface &$cacheability) {

    //$this->messenger->addStatus("Route: " . $route_name);

    switch($route_name) {
      // Hide "Test" tab and rename "Results" to "Registrations".
      case 'entity.node.canonical':
        unset($data['tabs'][0]['entity.node.webform.test_form']);
        $data['tabs'][0]['entity.node.webform.results']['#link']['title'] = t('Registrations');
      break;

      // Remove tabs from the personal contact form page.
      case 'entity.user.contact_form':
        unset($data['tabs'][0]);
      break;

      // Remove tabs from the user account page.
      case 'entity.user.canonical':
        unset($data['tabs'][0]['entity.user.edit_form']);
        unset($data['tabs'][0]['entity.webform_submission.user']);
        unset($data['tabs'][0]['devel.entities:user.devel_tab']);
        unset($data['tabs'][0]['role_delegation.edit_form']);
        unset($data['tabs'][0]['tac_lite.user_access']);
        unset($data['tabs'][0]['views_view:view.scheduler_scheduled_content.user_page']);
        unset($data['tabs'][0]['views_view:view.scheduler_scheduled_media.user_page']);
      break;

      // Change tab title on the "My Accounts" page.
      case 'view.events_free.free_events':
      case 'view.ride_routes.my_rides':
      case 'view.user_account.my_events':
      case 'view.user_account.my_payments':
        $data['tabs'][0]['entity.user.canonical']['#link']['title'] = 'Member Info';
      break;
    }
  }

  /**
   * Implements hook_preprocess_menu().
   */
  #[Hook('preprocess_menu')]
  public function bikeclub_preprocess_menu(&$variables) {

    // Convert menu to HTML if contains fontawesome icons (`fas fa-` or `fab fa-`).
    foreach ($variables['items'] as $menu_id => $menu) {

      if (array_key_exists('title', $menu)) {
        $title = $menu['title'];
        if (is_string($title) &&
           ( (strpos($title, 'fas fa-') > 0) ||
             (strpos($title, 'fab fa-') > 0) ||
             (strpos($title, 'fa fa-') > 0) )) {

            $variables['items'][$menu_id]['title'] = Markup::create('<i class="' . $title . '"></i>');
        }
      }
    }  
  } 
}
