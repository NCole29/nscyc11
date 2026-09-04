<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;
use Drupal\Core\Datetime\DrupalDateTime;


class FixWed {
	/**
	 * Re-save all Wednesday rotating recurring rides as rides.
	 */
	public static function convertWed() {

		/*
		// recurring rides for testing;
		$list = [455,464,465,466,472]; 
		$nids = Node::loadMultiple($list);
		*/

		$type = 'recurring_ride';
		$title = 'Wednesday Morning Rotating Ride';

		$nids = \Drupal::entityTypeManager()->getStorage('node')
		  ->loadByProperties([ 'type' => $type, 'title' => $title,]);

		$idlist = "";

		foreach ($nids as $nid) {

			$timestamp = $nid->field_datetime->value;
			$drupal_date = DrupalDateTime::createFromTimestamp($timestamp, 'UTC');

			$node = Node::create([
				'type' => 'ride',
				'title' => $nid->title,
				'status' => $nid->status,
				'created' => $nid->created,
				'changed' => $nid->changed,
				'uid' => $nid->uid,
			]);
			$node->field_additional_information = $nid->field_additional_information;		
			$node->field_date 					= $drupal_date->format('Y-m-d\TH:i:s');
			$node->field_dayofweek		 	= $nid->field_dayofweek;		
			$node->field_description	 	= $nid->field_description;
			$node->field_developed_by	 	= $nid->field_developed_by;	
			$node->field_gravel_category= $nid->field_gravel_category;
			$node->field_ride_leader	 	= $nid->field_ride_leader;
			$node->field_lunch_place	 	= $nid->field_lunch_place;
			$node->field_master         = $nid->field_master;
			$node->field_multiple_times	= $nid->field_multiple_times;
			$node->field_based_on       = $nid->field_based_on;
			$node->field_ride_picture	 	= $nid->field_ride_picture;	
			$node->field_activity		 		= $nid->field_activity;		
			$node->field_rwgps_routes	 	= $nid->field_rwgps_routes;
			$node->field_schedule_date	= $nid->field_schedule_date;
			$node->field_location		 		= $nid->field_location;
			$node->field_time			 			= $nid->field_time;

			// Set defaults.
			$node->field_registration = 0;	
				
			if ($nid->field_lunch_place == $nid->field_location) {
				$node->field_lunch_same = 1;
			}
			$node->save();

			// Save old and new ids;
			$old = "<a href='/node/" . $nid->id() . "'>" . $nid->id() . "</a>";
			$new = "<a href='/node/" . $node->id() . "'>" . $node->id() . "</a>";

			$idlist .=  "Old: " . $old . "  New: " . $new . "<br>";
		} 

		// Save list of old and new node ids with hyperlinks in a Basic Page.
		$now = \Drupal::time()->getRequestTime();

		$page = Node::create([
			'type' => 'page',
			'langcode' => 'en',
			'created' => $now,
			'changed' => $now,
			'uid' => 1,
			'title' => 'Old and New Wednesday Morning Rotating Rides',
			'field_type_of_page' => 1,
			'field_text' => [
				'format' => 'full_html',
				'value' => $idlist,
			],
			'status' => 1,
			'promote' => 0,
		]);
		$page->save();
	}
}
