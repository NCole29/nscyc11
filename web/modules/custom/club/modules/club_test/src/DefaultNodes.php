<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;

class DefaultNodes {

	/**
	 * Output default content for multiple Nodes.
	 */
	public static function getNodes($nids) {

    $nids = Node::loadMultiple($nids);

    echo '<br><h3>UUIDs for default NODES - copy to modules/custom/export_content/export_content.info.yml and enable module</h3>';
	  foreach ($nids as $nid) {
      $title = str_replace(' ','_',strtolower($nid->title->value)); 
      echo '<br>- ' . $nid->uuid->value;
    }

    echo "<br><h3>Copy to modules/custom/export_content/renamit.sh</h3>";    
    foreach ($nids as $nid) {
      $title = str_replace(' ','_',strtolower($nid->title->value)); 
      echo '<br>mv content/node/' . $nid->uuid->value . '.yml  content/node/' .  $title . '.yml';
    }

  }

 	/**
	 * Output default content for a single Node.
	 */
  public static function getNode($nid) {
    $nid = Node::load($nid);

    $title = str_replace(' ','_',strtolower($nid->title->value)); 

    echo '<br><h3>UUID for NODE - copy to modules/custom/export_content/export_content.info.yml and enable module</h3>';
    echo '<br>- ' . $nid->uuid->value;
    
    echo "<br><h3>Copy to modules/custom/export_content/renamit.sh</h3>";
    echo '<br>mv content/node/' . $nid->uuid->value . '.yml  content/node/' .  $title . '.yml';
  }
}