<?php

namespace Drupal\bikeclub_reports\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

class ReportHooks {
	
/**
 * Implements hook_help().
 */
  #[Hook('help')]
  public function reportHelp($route_name, RouteMatchInterface $route_match) {
  switch ($route_name) {
    case 'help.page.bikeclub_reports':
      $output = '';
      $output .= '<h3>' . t('About') . '</h3>';
      $output .= '<p>' . t("This module provides Annual membership counts calculated from CiviCRM <u>contribution</u> data.<br>CiviCRM membership data are not used because members have a single membership record that is updated upon renewal and membership may not be continuous. 
      <p><strong>Annual membership is necessarily a point-in-time measure. It is calculated as of year-end.</strong></p>");
      $output .= '<h3>' . t('Uses') . '</h3>';
      $output .= '<dl>';
      $output .= '<dt>' . t('View the Annual Membership Report') . '</dt>';
      $output .= '<dd>' . t('This report is displayed in a block. Upon installation, this block is placed on a Membership Statistics node along with blocks displaying current membership statistics from <a href="/admin/structure/views">View > CiviCRM Contacts</a>.') . '</dd>';
      $output .= '<dt>' . t('Process Historic Membership Contributions') . '</dt>';
      $output .= '</dl>';

      $output .= "<h3>Related Processes</h3>";
      $output .= "<table>";
      $output .= "<tr> <th>Task</th><th>Instructions</th></tr>";
      $output .= "<tr>";
      $output .= "<td>Enter current membership fees</td>";
      $output .= "<td>Go to <a href='/civicrm/admin/member/membershipType'>CiviCRM > Administer > CiviMember > Membership types</a>.</td></tr>";
      $output .= "<tr>";
      $output .= "  <td>Revise current membership fees</td>";
      $output .= "  <td>Put the site in maintenance mode, load the Annual Membership report to process existing records with the current membership fees, then revise the fees at <a href='/civicrm/admin/member/membershipType'>CiviCRM > Administer > CiviMember > Membership types</a>.</td>";
      $output .= "</tr></table>";

      return $output;
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function reports_cron(): void {

    $current_time = \Drupal::time()->getRequestTime();
    $current_month_day = \Drupal::service('date.formatter')->format($current_time, 'custom', 'm-d');
    $target_month_day = '12-31'; // Target date for execution.

    if ($current_month_day != $target_month_day) {
      return;
    }

    // Use the State API to track the last time the annual task was performed.
    $state = \Drupal::state();
    $last_execution_year = $state->get('bikeclub_reports.annual_stats_last_execution_year', 0);
    $current_year = (int) \Drupal::service('date.formatter')->format($current_time, 'custom', 'Y');
    
    // Execute on December 31st if task hasn't been run in the current year.
    if ($current_year > $last_execution_year) {

      $controller = \Drupal::service('bikeclub_reports.annual_stats');
      $controller->tabulate();

      \Drupal::logger('bikeclub_reports')->info('Running annual statistics for the year @year.', ['@year' => $current_year]);
     
      // Update the state variable to the current year.
      $state->set('bikeclub_reports.annual_stats_last_execution_year', $current_year);
    }
  }
}