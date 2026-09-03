<?php

namespace Drupal\bikeclub_reports\Controller;

use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FixMemberships {
	
	/**
	 * The database connection.
	 *
	 * @var \Drupal\Core\Database\Connection
	 */
	protected $connection;
	
	public function __construct(Connection $connection) {
      $this->connection = $connection;
    }
 
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
    );
   }
    
    
	/**
	 * Get count of membership payment records.
	 */
	public static function getCounts($when) {
		$memberships = $this->connection->query("SELECT COUNT(id) as num_Memberships FROM {civicrm_membership} ")
		->fetchALL();

		$payments = $this->connection->query("SELECT COUNT(id) as num_Payments FROM {civicrm_membership_payment} ")
		->fetchALL();

		$memberships = json_decode(json_encode($memberships), true);
		$payments    = json_decode(json_encode($payments), true);

		echo "<h3>#Records at $when: </h3>" .
		"<ul><li>civicrm_memberships: " . $memberships[0]['num_Memberships'] . '</li>' .
		    "<li>civicrm_membership_payments: " . $payments[0]['num_Payments'] . '</li></ul>';
	}

	/**
	 * Find contacts with multiple memberships in civicrm_membership table.
	 * Combine memberships by assigning all contributions to the CURRENT or most recent membership.
	 * Then delete memberships with no contributions.
	 * Number of payment records are unchanged because we just move them.
	 */
	public static function mergeMemberships() {
		$print = 0; // 1 = TESTING - print and do not update database

		// CiviCRM custom tables differ by installation - Must set them.
		// nsc.ddev.site:   TABLE = civicrm_value_contacts_8   COLUMN = fixed_multiple_membership_record_12
		// dev.nscyc, nscyc TABLE = civicrm_value_contacts_6   COLUMN = fixed_multiple_membership_record_8
		if (\Drupal::request()->getHost() == 'nsc.ddev.site') {
			$customTable = 8;
			$customColumn = 12;
		} else {
			$customTable = 6;
			$customColumn = 8;
		}

		fixmemberships::getCounts('START'); // Print starting record count.

		// Get list of contacts with multiple membership records and convert object to array.
		$contact_list = $this->connection->query("SELECT contact_id FROM {civicrm_membership} GROUP BY contact_id HAVING COUNT(contact_id) > 1")
		->fetchALL();
		$contacts = json_decode(json_encode($contact_list), true);

		$counter = 0; // # Contacts fixed
		$now = strtotime(date("Y-m-d")); // Needed for end-date adjustment when there are multiple current memberships

		// For each contact with two membership records, retrieve and process membership records.
		foreach ($contacts as $contact) {
			$counter++;
			$contact_id = reset($contact); // Convert to string.

			// Retrieve membership records and convert object to array.
			$member_obj = $this->connection->query("SELECT id, contact_id, join_date, start_date, end_date, status_id FROM {civicrm_membership} WHERE contact_id = :contact", [':contact' => $contact_id, ])
			->fetchAll();
			$memberships = json_decode(json_encode($member_obj), true);

			// Remove null values and transpose array columns (removing NULL from array does not change array key).
			$membership_ids = array_column($memberships, 'id');
			$join_dates     = array_diff(array_column($memberships, 'join_date'), array(null));
			$end_dates      = array_diff(array_column($memberships, 'end_date'), array(null));
			$status_ids     = array_diff(array_column($memberships, 'status_id'), array(null));

			// Get array index (key) of max(id) and last_end_date
			$last_id = array_search(max($membership_ids), $membership_ids);
			$last_end_date = array_search(max($end_dates), $end_dates);

			// Keep first join_date (need IF because dates may be NULL)
			if ( count($join_dates) > 0)  {
				$first_join_date = min($join_dates);
			}

			// If just one active membership, keep that "current" record.
			// If more than one active membership (New or Current) - sum "days to end" to set end date
			$num_active = 0;
			$days_to_end = 0;
			$new_enddate = '';

			if ( count($status_ids) > 0)  {
				foreach ($status_ids as $key => $status) {
					if ($status == '1' | $status == '2') {
						$num_active++;
						$current = $key; // Keeps the last one 
						$days_left = strtotime($end_dates[$key]) - $now;

						$days_to_end = $days_to_end + $days_left;
					}
				}

				if($days_to_end > 0) {
					$timestamp = $now + $days_to_end; 
					$new_enddate = date("Y", $timestamp) . '-' . date("m", $timestamp) . '-' . date("d", $timestamp);
				}
			}

			// Get array KEY for membership that we keep.
			// PHP treats zero like empty; since array keys start with zero, check if value is integer.
			if ($current) {
				$key = $current;
			} else if (is_int($last_end_date)) {
				$key = $last_end_date;
			} else {
				$key = $last_id;
			}
			$keep_id = $membership_ids[$key];


			// ******************  Print *******************************
			if ($print == 1) {
				echo '<h3>Contact id: ' . $contact_id . ' -- BEFORE FIX</h3>' ;
				!kint($memberships);
				echo	'Keys:<br>Current: ' . $current . '<br>Last enddate: '  . $last_end_date . '<br>Last ID: ' . $last_id . '<br>';
				echo 'Number active: ' . $num_active . '<br>New end date: ' . $new_enddate ; 

				// Retrieve membership to keep.
				$keep_obj = $this->connection->query("SELECT id, contact_id, join_date, start_date, end_date, status_id FROM {civicrm_membership} WHERE id = :mem_id", [':mem_id' => $keep_id, ])
				->fetchAll();
				$keep = json_decode(json_encode($keep_obj), true);

				echo '<br><br>KEEP THIS ONE'; 
				!kint($keep);
			}
			// ******************  End Print *******************************

			// ******************  UPDATE DATABASE *******************************
			if ($print == 0) {
				// Replace join date on KEPT membership record.
				$this->connection->update('civicrm_membership')
					->condition('id', $keep_id)
					->fields(['join_date' => $first_join_date ])
					->execute();

				// Associate all contributions (member dues) with the membership record that we keep.
				foreach ($membership_ids as $member_id) {
					$this->connection->update('civicrm_membership_payment')
					->condition('membership_id', $member_id)
					->fields(['membership_id' => $keep_id ])
					->execute();
				}

				// Delete old membership record 
				$this->connection->delete('civicrm_membership')
					->condition('contact_id', $contact_id, '=')
					->condition('id', $keep_id, '!=')
					->execute();


				// If new end date, replace end date and flag
				if($days_to_end > 0) {

					$this->connection->update('civicrm_membership')
						->condition('id', $keep_id)
						->fields(['end_date' => $new_enddate ])
						->execute();

					// Add a FLAG in custom table to indicate Membership end-date fix
					// If contact is not in the custom table -> INSERT, else UPDATE.
					$table = "civicrm_value_contacts_" . $customTable;
					$column = "'fixed_multiple_membership_record_" . $customColumn . "'";
							
					$custom_table_obj = $this->connection->query("SELECT * FROM {$table} WHERE entity_id = :contact", [':contact' => $contact_id, ])
							->fetchAll();
					$custom_table = json_decode(json_encode($custom_table_obj), true);

					if (empty($custom_table)) {
						$this->connection->insert($table)
							->fields([
							'entity_id'=> $contact_id,
							$column => 1,
						])->execute();
					} else {
						$this->connection->update($table)
						->condition('entity_id', $contact_id)
						->fields([$column => 1 ])
						->execute();
					}
				} 
			}

			// ******************  Print *******************************
			if ($print == 1) {
				echo '<br><br>AFTER FIX';

				$member_obj = $this->connection->query("SELECT id, contact_id, join_date, end_date, status_id FROM {civicrm_membership} WHERE contact_id = :contact", [':contact' => $contact_id, ])
				->fetchAll();
				$memberships = json_decode(json_encode($member_obj), true);

				!kint($memberships);
			}
		}	

		echo '<h3>Number of contacts fixed: ' . $counter . '</h3>';
		fixmemberships::getCounts('END'); // Print ending record count.
		echo "<h3>Program Completed</h3>";
    echo "<h2><a href='/civicrm-issues'>Return</a></h2>";
		die;			
	}
}