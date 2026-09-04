<?php

namespace Drupal\club_test;

class CleanPermissions {

	/**
	 * Remove invalid permissions that did not uninstall when module was disabled.
	 */
	public static function dropPermissions() {

      // Clean Permissions. Source: https://www.drupal.org/node/3193348
	    $entity_type_manager = \Drupal::entityTypeManager();

	    $permissions = array_keys(\Drupal::service('user.permissions')->getPermissions());

	    /** @var \Drupal\user\RoleInterface[] $roles */
	    $roles = $entity_type_manager->getStorage('user_role')->loadMultiple();

	    foreach ($roles as $role) {
	      $role_permissions = $role->getPermissions();
	      $differences = array_diff($role_permissions, $permissions);
        
	      if ($differences) {
	        foreach ($differences as $permission) {
	          echo '<br> ' . $permission;
	          $role->revokePermission($permission);
	        }
	        $role->save();
	      }
	    }
	}
}