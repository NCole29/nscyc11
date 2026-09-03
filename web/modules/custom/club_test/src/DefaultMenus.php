<?php

namespace Drupal\club_test;

class DefaultMenus {

	/**
	 * Output default content.
	 */
	public static function getMenu($menu) {

    $menu_items = \Drupal::entityTypeManager()->getStorage('menu_link_content')
    ->loadByProperties(['menu_name' => $menu]);

    echo "<br><h3>$menu menu items UUIDs - copy to modules/custom/export_content/export_content.info.yml and enable module</h3>";
    foreach ($menu_items as $item) {
      echo '<br>- ' . $item->uuid->value;
    }
    echo "<br><h3>Copy to modules/custom/export_content/renamit.sh</h3>";
    foreach ($menu_items as $item) {      
      $title = str_replace(' ','_',strtolower($item->title->value)); 
      echo '<br>mv content/menu_link_content/' . $item->uuid->value . '.yml  content/menu_link_content/' .  $title . '.yml';
    }
  }
}