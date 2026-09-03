<?php

namespace Drupal\bikeclub_ride_tools\Hook;

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for bikeclub_ride_tools.
 */
class RideToolsHelp {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function rideHelp($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bikeclub_ride_tools':
        $output = '';
        $output .= '<h2>' . t('About') . '</h2>';
        $output .= '<p>' . t('The <strong>Bikeclub Ride Tools module</strong> provides integration with RideWithGPS plus features to manage rides (available from "Ride tools" on the Administration menu).') . '</p>';

        $output .= '<h2>' . t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . t('<strong>RWGPS Integration</strong>') . '</dt>';
        $output .= '<dd>' . t('The rides content types (<strong>Ride</strong> and <strong>Recurring Ride</strong>) include a field for entering one or more RWGPS route numbers. When a ride is saved, custom code is executed ');
        $output .=  t('to retrieve RWGPS information (route name, distance, elevation, creation and change dates) via a RWGPS API call. These data are saved to a "club route" table in the Drupal database and displayed on ride pages. '); 
        $output .=  t('This requires a <a href="https://ridewithgps.com/api">RWGPS API key</a> which can be entered into site configuration using the RWGPS API key link below.');
        $output .= '</dd><p></p>';

        $output .= '<dt>' . t('<strong>Ride Tools</strong>') . '</dt>';
        $output .= '<dd>' . t('<strong>Cancel ride</strong> adds a cancellation notice to the top of the home page, the calendar, and the list of rides on the home page.');
        $output .= t('<p><strong>Nonmember waiver</strong> opens a webform to collect nonmember names and emails, if a paper waiver form is used at ride starts. ');
        $output .= t('Submitting this form automatically sends a follow-up email to the nonmember. To customize this automated email, go to Structure > Webforms > Nonmembers > Settings > Email/Handlers.</p>');

        $output .= '<p>' . t('<strong>View ride schedule</strong> provides a list of all calendar dates linked with scheduled rides, enabling users to quickly identify available dates. ') ;
        $output .= t('This tool can be used for advance planning by assigning ride leaders to dates before a full ride page is created.') . '</p>';
        $output .= '</dd>';

        $output .= '<h3>' . t('This module includes') . '</h3>';

        $output .= '<dd>' . t('Two custom entities which mostly operate "behind the scenes."') . '<ul>';
        $output .= '<li>' . t('<strong>Club route</strong> stores data retrieved from RWGPS.') . '</li>';
        $output .= '<li>' . t('<strong>Club schedule</strong> stores calendar dates. The data table is initially populated with three years of calendar dates. ');
        $output .= t('Over time, additional dates can be added with the <em>Add schedule dates</em> link below. The "Ride Schedule" View joins calendar dates with nonrecurring ride dates.') . '</li>';
        $output .= '</dd>';

        $output .= '</dd></dl>';
        return $output;
    }
  }

}
