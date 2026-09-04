<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;

class ResaveNodes {

	/**
	 * Re-save all content of a specific type.
	 */
	public static function loadNodes($node_type) {

	  // Get an array of all  node ids.
	  $nids = \Drupal::entityQuery('node')
		->accessCheck(FALSE)
		->condition('type', $node_type)
		->execute();

	  // Load all the nodes.
	  $nodes = Node::loadMultiple($nids);

	  foreach ($nodes as $node) {
			$node->save();
	  }
	}

}
