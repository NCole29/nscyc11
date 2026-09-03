<?php

namespace Drupal\club_test;

use Drupal\user\Entity\User;

class DefaultUsers {

	/**
	 * Output default content.
	 */
	public static function getUsers($uids) {

    $users = User::loadMultiple($uids);

    echo '<br><h3>UUIDs for default USERS - copy to modules/custom/export_content/export_content.info.yml and enable module</h3>';
	  foreach ($users as $user) {
		 	echo '<br>- ' . $user->uuid->value;
	  }

    echo "<br><h3>Copy to modules/custom/export_content/renamit.sh</h3>";
	  foreach ($users as $user) {
			$name = str_replace(' ','_',strtolower($user->name->value)); 
			echo '<br>mv content/user/' . $user->uuid->value . '.yml  content/user/' .  $name . '.yml';
	  }

	}
}