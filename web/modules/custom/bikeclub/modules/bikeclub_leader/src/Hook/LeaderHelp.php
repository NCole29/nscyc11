<?php

namespace Drupal\bikeclub_leader\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hook implementations for bikeclub_leader.
 */
class LeaderHelp {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function leaderHelp($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.bikeclub_leader':
        $output = '';
        $output .= '<h2>' . t('About') . '</h2>';
        $output .= '<p>' . t("The <strong>Bikeclub Leader module</strong> is used to  track the history of an club's leadership and automatically assign Drupal roles (website permissions) to current leaders for the duration of their terms.") . '</p>';

        $output .= '<h2>' . t('Uses') . '</h2>';
        $output .= '<dl>';

        $output .= '<dt>' . t('<strong>Club positions taxonomy</strong>') . '</dt>';

        $output .= '<dd>' . t("Use the <a href=':positions'>Club positions</a> taxonomy</strong> to <strong>define, categorize, and organize</strong> club positions. ", 
         [':positions' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'positions',])->toString()]);
        $output .= t("Leaders are displayed on web pages according to the order of positions within the taxonomy. Drag and drop positions to change their display order. ");
        $output .= t("<p>Check the <strong>Disabled</strong> box to prevent a position from being assigned in the future. <strong>DO NOT delete</strong> positions that have been used previously; deleting them will permanently remove those positions from the <strong>Past leaders</strong> display.");
        $output .= '</dd>';

       $output .= '<dt>' . t('<strong>Add Club leaders</strong>') . '</dt>';

        $output .= '<dd>' . t('Add club leaders by navigating to <a href=":leaders">People > Club leaders</a>, and clicking the "Add leader" button. ', [
          ':leaders' => Url::fromRoute('view.club_leaders.edit')->toString()
        ]);
        $output .= '<dd>' . t('Fields:') . '<ul>';
        $output .= '<li>' . t('<strong>Name (Autocomplete field)</strong>. Use this field to add leaders. The autocomplete field searches for an existing Drupal user account. ');
        $output .= t('This field is restricted to <em>Members</em> with a filter on the <a href=":eligible">Club leaders eligible</a> View. This is the only name field available when adding new leaders, thus ensuring that a Drupal account exists.',
          [':eligible' => Url::fromURI('internal:/admin/structure/views/view/club_leaders_eligible')->toString(),   ]) . '</li>';

        $output .= '<li>' . t('<strong>Edited name (Text field)</strong>. Edit a leader record to access this optional text field and standardize the display name (e.g., correcting capitalization or using a formal name instead of a nickname). If both fields are filled, the Edited Name is used for public display. ');
        $output .= t('This field should be used when manually loading past leaders who may no longer be members (e.g., during a data import via the Feeds module).'). '</li>';

        $output .= '<li>' . t("<strong>Start and end dates</strong> define the specific duration of a leader's term. These dates identify current vs. past leaders for display; they also control the enabling and disabling of user permissions via the assignment of Drupal roles.");
        $output .= t(' <strong>Do not extend the end date</strong>  when a leader is re-elected. It is crucial to create a new record for each separate term so that the list of past leaders by time period is complete.');
        $output .= '</ul></dd>';

        $output .= '<dt>' . t('<strong>View Club leaders</a></strong>') . '</dt>';
        $output .= '<dd>' . t('The <a href=":viewcl">Club Leaders View</a> provides public pages. Add a link to the main menu if it does not already exist, with link "/club-leaders". This page provides tabs for current and past leaders.',
         [':viewcl' => Url::fromRoute('view.club_leaders.current_leaders')->toString()]) .  '</dd>';
        $output .= '</dl>';

        $output .= '<h3>' . t('This module includes') . '</h3>';
        $output .= '<ul>';
        $output .= '<li>The <strong>ClubLeader</strong> custom entity.</li>';
        $output .= '<li>A form hook to customize the <em>Club positions</em> taxonomy overview page and to hide the edited name field when adding a new leader.</li>';
        $output .= '<li>Custom code to assign a Drupal Role to a club leader when the leader record is saved, and remove assigned Drupal roles from users when their leader term concludes. 
        The "removal" code identifies leaders whose term has ended every time a leader record is saved since saving a new leader generally corresponds with the end of existing terms.</li>';
        $output .= '</ul';

        return $output;
    }
  }

}
