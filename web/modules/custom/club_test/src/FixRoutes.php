<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;

class FixRoutes {
	/**
	 * Save ride starting location in club_route field_ride_start.
	 * One time fix. Hereafter this is done in club_ride.module when a ride is save.
	 */
	public static function resaveRides($node_type) {

		// Get an array of all  node ids.
		$nids = \Drupal::entityQuery('node')
		->accessCheck(FALSE)
		->condition('type', $node_type)
		->execute();
		
		$db = \Drupal::database();

		foreach($nids as $nid) {
			// Get location target id. 
			$location_obj = $db->query("SELECT field_location_target_id FROM {node__field_location} WHERE entity_id = :nidlist", [':nidlist' => $nid,])
			->fetch();

			// Convert object to string.
			$location = json_decode(json_encode($location_obj), true);

			// If location is filled, add it to route table.
			if($location) {
				$location = reset($location); // Convert to string.

				// Get routes from ride node and convert object to array.
				$route_obj = $db->query("SELECT field_rwgps_routes_target_id FROM {node__field_rwgps_routes} WHERE entity_id = :nidlist", [':nidlist' => $nid,])
				->fetchALL();
				$routes = json_decode(json_encode($route_obj), true);

				// INSERT location in club_route if it does not exist.
				foreach($routes as $route) {
					$route = reset($route);

					$route_ride_start = $db->query("SELECT field_ride_start_target_id FROM {club_route__field_ride_start} WHERE entity_id = :route", [':route' => $route,])
					->fetch();

					if(!$route_ride_start) {
						$fields = array(
							'bundle' => 'club_route',
							'deleted' => 0,
							'entity_id' => $route,
							'revision_id' => $route,
							'langcode' => 'en',
							'delta' => 0,
							'field_ride_start_target_id' => $location,
						);

						$db->insert('club_route__field_ride_start')
							->fields($fields)
							->execute();
					}
				}
			}
		}
	}
}
