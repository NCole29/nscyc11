<?php

namespace Drupal\club;

/**
 * Check roles and return if a Club leader.
 *
 * Method is called by club_forum.module
 *
 */
class CheckRoles {

  public static function isLeader() {

    $leader_roles[] = 'administrator';

    // Get array of roles assigned to Club Leaders.
    $positions = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties([
          'vid' => 'positions',
      ]);
       
    // Add position roles to leader_roles array.
    foreach ($positions as $position) {
      $role = $position->field_website_role->target_id;
      $leader_roles[] = $role;
    }

    // Get all roles assigned to current user and check if user is a club leader.
    $user_roles = \Drupal::currentUser()->getRoles();
    $isLeader = 0;
    foreach ($user_roles as $role) {
      if (in_array($role, $leader_roles)) {
        $isLeader = 1;
      }
    }
    return $isLeader;
  }
}


