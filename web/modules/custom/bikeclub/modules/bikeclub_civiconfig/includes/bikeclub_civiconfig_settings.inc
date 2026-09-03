<?php

use Drupal\Component\Serialization\PhpSerialize;

/**
 * @file
 * Settings for CiviCRM.
 */

function bikeclub_civiconfig_settings() {

  // Settings for CiviCRM - configure via database changes.
  $db = \Drupal::database();

  /*************************************************
   Config checklist > Localization
   Default country (US), available countries (CA,US)
   *************************************************/
  $settings = [
    ['countryLimit', ["1039","1228"] ],
    ['defaultContactCountry', "1228" ],
    ['pinnedContactCountries', ["1039","1228"]],
    ['contact_view_options', [1,3,4,5,6,7,8,9]],
  ];

  // Delete and insert (not sure how to use IF EXISTS then UPDATE).
  foreach ($settings as $setting) {
    $db->delete('civicrm_setting')
      ->condition('name', $setting[0]);

    $db->insert('civicrm_setting')
      ->fields([
        'name' => $setting[0],
        'value' => PhpSerialize::encode($setting[1]),
        'domain_id' => 1,
        'is_domain' => 1,
      ])
      ->execute();
  }

  // Delete U.S. Territories from State list (setting is_active = 0 has no effect)
  $territories = ['AS','GU','MP','PR','VI','UM','AE','AA','AP'];

  $db->delete('civicrm_state_province')
    ->condition('country_id', 1228)
    ->condition('abbreviation', $territories, 'IN')
    ->execute();

  /**********************************************
   Config checklist > Organization name and email
   **********************************************/
  // Get from Drupal config
  $config = \Drupal::config('system.site');
  $site_name = $config->get('name');
  $site_email = $config->get('mail');

  // Set in CiviCRM tables
  $db->update('civicrm_domain')
    ->condition('id', 1, '=')
    ->fields(['name' => "$site_name"])
    ->execute();
  $db->update('civicrm_email')
    ->condition('id', 1, '=')
    ->fields(['email' => "$site_email"])
    ->execute();

  $db->update('civicrm_contact')
    ->condition('id', 1, '=')
    ->condition('contact_type', 'Organization', '=')
    ->fields([
      'sort_name' => "$site_name",
      'display_name' => "$site_name",
      'legal_name' => "$site_name",
      'organization_name' => "$site_name"
      ])
    ->execute();

  /*
   Config checklist > Keep default Components, Search Settings, Misc, Outbound email
   (editing Quicksearch options creates errors)
  */

  /******************************************************
   Config checklist >
   Display preferences, Address settings, Search settings
   ******************************************************/
  $settings = [
    ['contact_view_options', "13456789"],
    ['contact_edit_options', "124678141516"],
    ['advanced_search_options',"1256789101516171819"],
    ['menubar_color',"#800000"],
    ['quicksearch_options',["sort_name","contact_id","first_name","last_name","email","phone_numeric","street_address","city","postal_code"]],
  ];

  /* took this out of settings
['mailing_format',
"{contact.addressee}
{contact.street_address}
{contact.supplemental_address_1}
{contact.city}{, }{contact.state_province}{ }{contact.postal_code}"],
['address_format',
"{contact.address_name}
{contact.street_address}
{contact.supplemental_address_1}
{contact.city}{, }{contact.state_province}{ }{contact.postal_code}"],
['address_options', "1256910"],
*/

  // Delete and insert (not sure how to use UPDATE)
  foreach ($settings as $setting) {
    $db->delete('civicrm_setting')
      ->condition('name', $setting[0])
      ->execute();

    $db->insert('civicrm_setting')
      ->fields([
        'name' => $setting[0],
        'value' => PhpSerialize::encode($setting[1]),
        'domain_id' => 1,
        'is_domain' => 1,
      ])
      ->execute();
  }

  /********************************
   Config checklist > Contact types
   ********************************/
  $db->update('civicrm_contact_type')
    ->condition('name', 'Household')
    ->fields(['is_active' => 0])
    ->execute();

  /********************************************
   Customize data and screens > Navigation menu
   ********************************************/
  $table = 'civicrm_navigation';

  // Get menu IDs & then condition on parent menu
  $menus = ['Search', 'Contacts', 'Contributions', 'Events', 'Mailings', 'Memberships', 'Reports'];

  foreach ($menus as $menu) {
    $query = $db->select($table, 'c')
      ->fields('c', ['id'])
      ->condition('c.parent_id', NULL, 'IS NULL')
      ->condition('c.name', $menu);
    $id = $query->execute()->fetch(); 
    $id = get_mangled_object_vars($id);
    $id = reset($id);

    $menu_id[$menu] = $id;
  }

  $disable['Search'] = [];
  $disable['Contacts'] = ["New Household","New Organization","Contact Reports", "New Activity", "Import Activities","New Group",];
  $disable['Contributions'] = ["New Contribution","Find Contributions","Contribution Reports","Batch Data Entry",
                               "New Contribution Page","Personal Campaign Pages","Premiums","New Price Set"];
  $disable['Events'] = ["Event Reports","New Event","Personal Campaign Pages","New Price Set"];
  $disable['Mailings'] = ["Mailing Reports","From Email Addresses",  "New SMS","Find Mass SMS","New A/B Test"];
  $disable['Memberships'] = ["Membership Reports","New Price Set","Manage Price Sets"];
  $disable['Reports'] = [];

  // Some of these items are disabled but the separator line shows if not removed
  $drop_line['Search'] = ['Find Memberships', 'Find Participants'];
  $drop_line['Contacts'] = ["Manage Groups"];
  $drop_line['Contributions'] = ["Manage Contribution Pages"];
  $drop_line['Events'] = ["Event Templates"];
  $drop_line['Mailings'] = ["Manage A/B Tests"];
  $drop_line['Memberships'] = [];
  $drop_line['Reports'] = ["Pledge Reports","All Reports","My Reports","Case Reports"];

  $add_line['Search'] = ['Full-text Search'];
  $add_line['Contacts'] =[];
  $add_line['Contributions'] = [];
  $add_line['Events'] = ['Find Participants'];
  $add_line['Mailings'] = ['Archived Mailings'];
  $add_line['Memberships'] = ['Find Memberships'];
  $add_line['Reports'] = ['Membership Reports'];

  // Disable menu items, drop and add separator lines
  foreach($menus as $menu) {
    foreach($disable[$menu] as $item) {
      $db->update($table)
        ->condition('parent_id', $menu_id[$menu])
        ->condition('name', $item)
        ->fields(['is_active' => 0 ])
        ->execute();
    }
    foreach($drop_line[$menu] as $drop) {
      $db->update($table)
        ->condition('parent_id', $menu_id[$menu])
        ->condition('name', $drop)
        ->fields(['has_separator' => 0 ])
        ->execute();
    }
    foreach($add_line[$menu] as $add) {
      $db->update($table)
        ->condition('parent_id', $menu_id[$menu])
        ->condition('name', $add)
        ->fields(['has_separator' => 1 ])
        ->execute();
    }
  }

  /**************************************************
   Add "Home" to return to Home page and Drupal menu.
   **************************************************/
  $db->insert($table)
    ->fields([
      'domain_id' => 1,
      'label'=> 'Home',
      'name'=> 'Home',
      'url' => '/',
      'icon' => 'crm-i fa-home',
      'permission' => 'access CiviCRM',
      'permission_operator' => 'AND',
      'is_active' => 1,
      'has_separator' => 0,
      'weight' => 0,
      ])
    ->execute();

  /**********************************
   Customize data and screens > Tags
   **********************************/
  $db->delete('civicrm_tag')
    ->condition('name', 'Volunteer', '!=')
    ->execute();

  $db->insert('civicrm_tag')
    ->fields([
      'name' => 'Create Drupal User',
      'label' => 'Create Drupal User',
      'description' => 'Tag to create Drupal user account',
      'is_selectable' => 1,
      'is_reserved' => 0,
      'is_tagset' => 0,
      'used_for' => 'civicrm_contact',
      ])
    ->execute();

  /*************************************************************
   Customize data and screens > Dropdown options > Location type
   *************************************************************/
  $db->update('civicrm_location_type')
    ->condition('name', ['Work', 'Main', 'Other'], 'IN')
    ->fields(['is_active' => 0])
    ->execute();

  /********************************
   System settings > Scheduled Jobs
   ********************************/
  $jobs = ['CiviCRM Update Check', 'Send Scheduled Mailings', 'Fetch Bounces', 'Rebuild Smart Group Cache',
     'Update Membership Statuses' ,'Clean-up Temporary Data and Files'];
  $db->update('civicrm_job')
  ->condition('name', $jobs, 'IN')
  ->fields(['is_active' => 1])
  ->execute();

  /*******************************************************************************
   System settings > Option groups
   Event Type, Participant Role, Address Options, Phone Type, From Email addresses
   *******************************************************************************/

  // Get IDs for option_groups, then use IDs to query option_values
  $groups = ['event_type', 'participant_role', 'addressee', 'phone_type', 'from_email_address' ];

  foreach ($groups as $group) {
    $query = $db->select('civicrm_option_group', 'c')
      ->fields('c', ['id'])
      ->condition('c.name', $group);
    $id = $query->execute()->fetch(); 
    $id = get_mangled_object_vars($id);
    $id = reset($id);

    $option_group[$group] = $id;
  }

  $table = 'civicrm_option_value';

  $db->update($table)
    ->condition('option_group_id', $option_group['event_type'])
    ->condition('label', 'Conference')
    ->fields(['label' => 'Century', 'name' => 'Century'])
    ->execute();
  $db->update($table)
    ->condition('option_group_id', $option_group['event_type'])
    ->condition('label','Exhibition')
    ->fields(['label' => 'Function', 'name' => 'Function'])
    ->execute();
  $db->update($table)
    ->condition('option_group_id', $option_group['event_type'])
    ->condition('label','Fundraiser')
    ->fields(['label' => 'Other', 'name' => 'Other'])
    ->execute();
  $db->update($table)
    ->condition('option_group_id', $option_group['participant_role'])
    ->condition('label', 'Attendee')
    ->fields(['label' => 'Participant', 'name' => 'Participant'])
    ->execute();

  $newAddressee = "{contact.first_name}{ }{contact.last_name}";
  $db->update($table)
    ->condition('option_group_id', $option_group['addressee'] )
    ->condition('filter', 1)
    ->fields(['label' => $newAddressee, 'name' => $newAddressee ])
    ->execute();

  $db->update($table)->condition('option_group_id', $option_group['phone_type'])
    ->condition('name', ['Fax', 'Pager', 'Voicemail'], 'IN')
    ->fields(['is_active' => 0])
    ->execute();

  // From email addresses
  $domain = substr($site_email, strpos($site_email, '@') + 1);
  $info = '"' . $site_name . '"' . "<info@" . $domain . ">";
  $membership = '"Membership"<membership@' . $domain . ">";

  $db->update($table)
  ->condition('option_group_id', $option_group['from_email_address'])
  ->condition('value', "1")
  ->fields(['label' => $info,'name' => $info,])
  ->execute();

  $db->insert($table)
    ->fields([
      'option_group_id' => $option_group['from_email_address'],
      'label' => $membership,
      'name' => $membership,
      'value' => "2",
      'weight' => 2,
      'domain_id' => 1,
      ])->execute();

  /************************
   Name and address profile
   ************************/
  $table = 'civicrm_uf_field';

  // Remove '(Home)' from field labels
  $db->update($table)
    ->condition('uf_group_id', 1, '=')
    ->expression('label', "REPLACE(label, :old_value, :new_value)", [
      ':old_value' => '(Home)',
      ':new_value' => '',
    ])
    ->execute();

  // Change weight to reorder from postal_code, country, state_province
  $db->update($table)
    ->condition('field_name', 'state_province', '=')
    ->fields(['weight' => 5])
    ->execute();
  $db->update($table)
    ->condition('field_name', 'postal_code', '=')
    ->fields(['weight' => 6])
    ->execute();
  $db->update($table)
    ->condition('field_name', 'country', '=')
    ->fields(['weight' => 7])
    ->execute();

 /* This throws error: Undefined array key "phone_ext-Primary-1"
     Get same error when adding phone manually. 
     But not needed since we get phone through membership webform.
 
  $db->insert($table)
    ->fields([
      'uf_group_id'=> 1,
      'is_active' => 1,
      'weight' => 8,
      'field_name' => 'phone',
      'phone_type_id' => 1,
      'label' => 'Phone',
      'field_type' => 'Contact',
      ])->execute();
 */
}
