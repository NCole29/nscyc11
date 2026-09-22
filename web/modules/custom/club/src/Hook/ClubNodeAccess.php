<?php

declare(strict_types=1);

namespace Drupal\club\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

class ClubNodeAccess {

  /**
   * Implements hook_ENTITY_TYPE_access().
   * 
   */
  #[Hook('node_access')]
  function nodeAccess(NodeInterface $node, $operation, AccountInterface $account): AccessResultInterface {

    // Deny edit access for blank home page (this enables multiple block placement without a frontpage view)
    $target_nid = 11274; 
    
    if ($node->id() == $target_nid && $operation != 'view') {
      // Deny edit access for everyone 
      return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();
    }

    // Allow edit/delete access for ride leaders.
    if (!$node->hasField('field_ride_leader')) {
      return AccessResult::neutral();
    }

    $type = $node->bundle();
    $ids = array_column($node->get('field_ride_leader')->getValue(), 'target_id');

    $isCoAuthor = AccessResult::allowedIf(in_array($account->id(), $ids, TRUE))
      ->cachePerUser()
      ->addCacheableDependency($node);

    switch ($operation) {
      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit own ' . $type . ' content')
          ->andIf($isCoAuthor);

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete own ' . $type . ' content')
          ->andIf($isCoAuthor);

      default:
        $access = AccessResult::neutral();
    }
    return $access;
  }

}
