<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;

class AddEventDates {

	/**
	 * Re-save all content of a specific type.
	 * Copy the Start date of the Smart Date Range into a Drupal Date field.
	 */
	public static function addEventDates() {

	  // Get an array of node ids.
	  $nids = \Drupal::entityQuery('node')
		->accessCheck(FALSE)
		->condition('type', 'event')
		->execute();
	
		$db = \Drupal::database();

		foreach($nids as $nid) {
			// There is one NODE and one date for each event.
			$nid = $db->query("SELECT nid, vid FROM {node} WHERE nid = :nidlist", [':nidlist' => $nid,])
			->fetch();

			// There are multiple field_datetimes for the recurring ride - so fetchALL.
			$node = $db->query("SELECT bundle,deleted,entity_id,revision_id,langcode,delta,field_datetime_value FROM {node__field_datetime} WHERE entity_id = :nidlist", [':nidlist' => $nid,])
			->fetch();

			//Convert timestamp to Drupal date.
			//$field_date = date('Y-m-d\TH:i:s', $node->field_datetime_value);
			$field_date = DrupalDateTime::createFromTimestamp($node->field_datetime_value);

			// INSERT Drupal date into field_date.
			if ($field_date) {

				$fields = array(
					'bundle' => $node->bundle,
					'deleted' => $node->deleted,
					'entity_id' => $node->entity_id,
					'revision_id' => $node->revision_id,
					'langcode' => $node->langcode,
					'delta' => $node->delta,
					'field_date_value' => $field_date,
				);

				$db->insert('node__field_date')
					->fields($fields)
					->execute();
				$db->insert('node_revision__field_date')
					->fields($fields)
					->execute();
			}
		}


	}
}
