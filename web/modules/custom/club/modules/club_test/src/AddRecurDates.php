<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;
use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Datetime\DateHelper;

class AddRecurDates {

	/**
	 * Delete all recurring_dates.
	 */
	public static function dropRecurDates() {
		// recurring_dates field_node_id = recurring_ride nodeID.  
    $ids = \Drupal::entityQuery('node')
      ->condition('type', 'recurring_dates')
      ->accessCheck(FALSE)
      ->execute();

    $storageHandler = \Drupal::entityTypeManager()->getStorage('node');
    $entities = $storageHandler->loadMultiple($ids);

		$i = 0;
    foreach ($entities as $entity) {
			$i++;
      $entity->delete();
    }
		echo $i . ' Recurring dates were deleted.'; die;
	}

	/**
	 * Re-save all recurring_rides to re-generate recurring_dates.
	 * Copy the Start date of the Smart Date Range into a Drupal Date field.
	 */
	public static function addRecurDates() {

	  // Get an array of node ids.
	  $nids = \Drupal::entityQuery('node')
		->accessCheck(FALSE)
		->condition('type', 'recurring_ride')
		->execute();
	
		$db = \Drupal::database();

		foreach($nids as $nid) {
			// There is one NODE for the recurring ride (fetch).
			$node = $db->query("SELECT nid, langcode, status, title FROM {node_field_data} WHERE nid = :nidlist", [':nidlist' => $nid,])
			->fetch();

			// There is one LOCATION for the recurring ride (fetch).
			$location = $db->query("SELECT field_location_target_id FROM {node__field_location} WHERE entity_id = :nidlist", [':nidlist' => $nid,])
			->fetch();

			// There are multiple field_datetimes for the recurring ride (fetchALL).
			$dates = $db->query("SELECT entity_id, deleted, field_datetime_value FROM {node__field_datetime} WHERE entity_id = :nidlist", [':nidlist' => $nid,])
			->fetchALL();


			// CREATE 'recurring_dates' nodes, with Drupal dates instead of smart dates and one "node" per instance.
			foreach($dates as $instance) {

				if ($instance->deleted == 0) {

					//Convert timestamp to Drupal date.
					$date = DateTimePlus::createFromTimestamp($instance->field_datetime_value,'UTC');
					$instance = $date->format('Y-m-d\TH:i:s');
					$dayofweek = DateHelper::dayOfWeek($instance);

					$new_node = Node::create([
						'type' => 'recurring_dates',
						'status' => $node->status,
						'langcode' => $node->langcode,
						'title' => $node->title,
						'field_recurid' => $nid,
						'field_location' => $location->field_location_target_id,
						'field_date' => $instance,
						'field_dayofweek' => $dayofweek,
					]);
					$new_node->save();
				}
			}
		}

	}
}
