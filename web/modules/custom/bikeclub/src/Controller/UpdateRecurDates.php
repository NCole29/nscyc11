<?php

namespace Drupal\bikeclub\Controller;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\DateTimePlus;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Calendar is very slow with Smart Dates and faster with Drupal dates, but
 *  recurring rides needs smart_date_recur.
 * Solution: insert one record per recurring ride date into Recurring Dates content type 
 *   title          = ride name
 *   field_recurid  = entity reference for recurring ride
 *   field_location = starting location ID 
 *   field_date     = Drupal date (instead of Smart date)
 *   field_cancel   = cancellation indicator is updated by UpdateCancellation.php
 *
 * In club_ride.module:
 *  hook_insert calls addDates() for new recurring dates
 *  hook_presave calls deleteDates() and addDates() for existing rides with updated Smart date
 * 
 * Recurring ride registrations are "attached" to ride_date nodes, so we can't delete past dates
 */

class UpdateRecurDates {

	public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
  }

	/**
	 * Delete future dates prior to replacing them.
	 */
	public function deleteDates($nid) {

		$storage_handler = $this->entityTypeManager->getStorage('node');

		$recurids = $storage_handler->getQuery()
		  ->accessCheck(FALSE)
  		->condition('field_recurid', $nid)
  		->execute();

		if (!empty($recurids)) {
			$recur_dates = $storage_handler->loadMultiple($recurids);
	 	  $now = time();
			$rdates = [];
			
			foreach ($recur_dates as $rdate) {
				$date = $rdate->get('field_date')->value;

				if (strtotime($date) > $now) {
					$rdates[] = $rdate;
				} 
			}
			if (!is_null($rdates)) {
			 $storage_handler->delete($rdates); 
			}
		}
	}

	/**
	 * CREATE one 'recurring_date' node for each recurring_ride instance
	 *  using Drupal dates instead of Smart Date timestamp. 
	 */
	public function addDates($node, $all) {
		$dates  = $node->field_datetime;
		$now = time();

		foreach($dates as $date) {

			// Add all dates (all=1) or replace future dates (all=0).
			$mindate = ($all == 1) ? 0 : $now;

			if ($date->value > $mindate) {
			
				// If no timezone conversion, 4 hrs are subtracted assuming timestamp is UTC.
				// Specify 'default' timezone and Drupal converts that to UTC upon save.

				// Get the default timezone value.
				$datetime = DateTimePlus::createFromTimestamp($date->value,'UTC');
				$instance = $datetime->format('Y-m-d\TH:i:s');
				$dayofweek = DateHelper::dayOfWeek($instance);

				$drupal_date_time = new DrupalDateTime('@' . $date->value, 'America/New_York'); 

				$storage_handler = $this->entityTypeManager->getStorage('node');

				$new_node = $storage_handler->create([
					'type' => 'recurring_dates',
					'status' => '1',
					'langcode' => 'en',
					'title' => $node->title->value,
					'field_recurid' => $node->id(),
					'field_location' => $node->field_location->target_id,
					'field_date' => $drupal_date_time->format('Y-m-d\TH:i:s'),
					'field_dayofweek' => $dayofweek,
				]);
				$new_node->save();
			}
		}
	}
}

