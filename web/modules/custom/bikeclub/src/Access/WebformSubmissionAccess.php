<?php

namespace Drupal\bikeclub\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;

class WebformSubmissionAccess implements AccessInterface {

  /**
   * Grant webform submisssion access if user in field_registrations_visible or field_ride_leader.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
  
    if ($route_match->getRouteName() === 'entity.node.webform.results_submissions') {

      $node = $route_match->getParameter('node');

      if (!$account->isAuthenticated()) {
        return AccessResult::neutral();
      }
      elseif ($account->hasPermission('view webform submissions any node')) {
        return AccessResult::allowed();
      } 
      elseif ($account->hasPermission('view webform submissions own node') and $account->id() === $node->getOwnerId()) {
        return AccessResult::allowed();
      }
      else {
        // Grant access if user is a ride leader or is granted access to event/webform submissions.
        // Authenticated users must have permission to 'View webform submissions for own node'.
        $fields = [
          'field_ride_leader',
          'field_webform_results',
        ];

        foreach ($fields as $field) {
          if ($node->hasField($field)) {
            $allowed_access = array_column($node->$field->getValue(), 'target_id');
          } 
        }

        if (!empty($allowed_access) && in_array($account->id(), $allowed_access, TRUE)) {
          $allowedAccess = AccessResult::allowed()
            ->cachePerUser()
            ->addCacheableDependency($node);

          return AccessResult::allowedIfHasPermission($account, 'view webform submissions own node')
            ->andIf($allowedAccess);          
        } else {
          return AccessResult::neutral();
        }
      } 
    }
  }
}