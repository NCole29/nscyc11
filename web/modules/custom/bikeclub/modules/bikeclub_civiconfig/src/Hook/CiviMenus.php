<?php

declare(strict_types=1);

namespace Drupal\bikeclub_civiconfig\Hook;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;

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
   * Implements hook_menu_local_actions_alter().
   */
  #[Hook('menu_local_actions_alter')]
  public function bikeclub_menu_local_actions_alter(&$local_actions) {
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

    if ($route_name == 'entity.user.canonical') {
      // Check membership status and display message if not current.
        $data['tabs'][0]['entity.user.canonical']['#link']['title'] = 'Member Info';
        $current_user = $this->currentUser->getAccount();
        $user_id = $current_user->id();
        $this->checkMembership($user_id);
    }
  }

  function checkMembership($user_id) {
    $db = $this->connection;
    $contact_id = $db->select('civicrm_uf_match', 'ufm')
      ->fields('ufm', ['contact_id'])
      ->condition('ufm.uf_id', $user_id)
      ->execute()
      ->fetchField();

    if ($contact_id) {
      $status_id = $db->select('civicrm_membership', 'cm')
        ->fields('cm', ['status_id'])
        ->condition('cm.contact_id', $contact_id)
        ->execute()
        ->fetchField();

      if ($status_id > 2) {
        $this->messenger->addError("Your membership is not current.");
      }
    }
  }

}
