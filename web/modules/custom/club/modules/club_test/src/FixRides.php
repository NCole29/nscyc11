<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;

class FixRides {
	/**
	 * Re-save all content of a specific type using db query because its less CPU-intensive than node->save.
	 * This adds the default value for a new field.
	 */
	public static function resaveRides($node_type) {

		// Get an array of all  node ids.
		$nids = \Drupal::entityQuery('node')
		->accessCheck(FALSE)
		->condition('type', $node_type)
		->execute();

		$db = \Drupal::database();

		foreach($nids as $nid) {
			$ride = $db->query("SELECT nid, vid FROM {node} WHERE nid = :nidlist", [':nidlist' => $nid,])
			->fetch();

			$field_master = $db->query("SELECT entity_id FROM {node__field_master} WHERE entity_id = :nidlist", [':nidlist' => $nid,])
			->fetch();

			// INSERT field if it does not exist.
			if (!$field_master) {
				$fields = array(
					'bundle' => $node_type,
					'deleted' => 0,
					'entity_id' => $ride->nid,
					'revision_id' => $ride->vid,
					'langcode' => 'en',
					'delta' => 0,
					'field_master_value' => 1,
				);

				$db->insert('node__field_master')
					->fields($fields)
					->execute();
				$db->insert('node_revision__field_master')
					->fields($fields)
					->execute();
			}
		}
	}
}
