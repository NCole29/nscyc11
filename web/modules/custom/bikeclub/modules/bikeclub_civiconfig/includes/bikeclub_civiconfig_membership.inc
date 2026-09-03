<?php

use Drupal\Component\Serialization\PhpSerialize;

/**
 * @file
 * Configurations set via database changes.
 * Membership types, payment processor, and contribution page (requires price set, price field, price field values).
 */

function bikeclub_civiconfig_membership() {
  // Get from Drupal config
  $site_name = \Drupal::config('system.site')->get('name');
  $site_email = \Drupal::config('system.site')->get('mail');
  $now = \Drupal::time()->getRequestTime();

  $db = \Drupal::database();

  /****************
   Membership types
   ****************/
  $storage = \Drupal::entityTypeManager()->getStorage('civicrm_membership_type');
  $member_types = [
    ['Individual 1-year', 10, 1, 'year', 'rolling' ],
    ['Individual 2-years', 20, 2, 'year', 'rolling' ],
  ];
  foreach ($member_types as $type) {
    $type = $storage->create([
      'domain_id' => 1,
      'name' => $type[0],
      'member_of_contact_id' => 1,
      'financial_type_id' => 2,
      'minimum_fee' => $type[1],
      'duration_interval' => $type[2],
      'duration_unit' => $type[3],
      'period_type' => $type[4],
      'visibility' => 'Public',
      'auto_renew' => 0,
      'is_active' => 1
    ]);
    $type->enforceIsNew();
    $type->save();
  }

  /*****************
   Payment processor
   *****************/
  $paypals = [
    [0, 'https://www.paypal.com/', 'https://www.paypal.com/' ],
    [1, 'https://www.sandbox.paypal.com/', 'https://www.sandbox.paypal.com/'],
  ];
  $storage = \Drupal::entityTypeManager()->getStorage('civicrm_payment_processor');

  foreach($paypals as $paypal) {
    $paypal = $storage->create([
        'domain_id' => 1,
        'name' => 'PayPal',
        'description' => 'Paypal Standard',
        'payment_processor_type_id' => 1,
        'is_active' => 1,
        'is_default' => 1,
        'is_test' => $paypal[0],
        'user_name' => $site_email,
        'url_site' => $paypal[1],
        'url_recur' => $paypal[2],
        'class_name' => 'Payment_PayPalImpl',
        'billing_mode' => 4,
        'is_recur' => 1,
    ]);
    $paypal->enforceIsNew();
    $paypal->save();
    $payId[] = $paypal->id();
  }

  /************************************************************************
   Customize data and screens > Custom fields > Emergency contact & waivers
   ************************************************************************/
  $group_storage = \Drupal::entityTypeManager()->getStorage('civicrm_custom_group');
  $groups = [
    ['Emergency Contact Info','Individual',0],
    ['Member waiver','Membership',1],
    ['Participant waiver','Participant',1],
  ];
  foreach($groups as $group) {
    $group = $group_storage->create([
      'type' => 'civicrm_custom_group',
      'title' => $group[0],
      'extends' => $group[1],
      'style' => 'Inline',
      'collapse_display' => $group[2],
    ]);
    $group->enforceIsNew();
    $group->save();
    $groupId[] = $group->id();
  }

  $field_storage = \Drupal::entityTypeManager()->getStorage('civicrm_custom_field');
  $fields = [
    [$groupId[0],'Name','String','Text'],
    [$groupId[0],'Phone','String','Text'],
    [$groupId[1],'Agree to the waiver','Int','Radio'],
    [$groupId[2],'Agree to the waiver','Int','Radio'],
  ];
  foreach($fields as $field) {
    $field = $field_storage->create([
      'custom_group_id' => $field[0],
      'name' => $field[1],
      'label' => $field[1],
      'data_type' => $field[2],
      'html_type' => $field[3],
    ]);
    $field->enforceIsNew();
    $field->save();
  }

  /* 'Agree to the waiver' is a radio field added to civicrm_custom_field - needs 'Yes' response value.
    civicrm_custom_field populates civicrm_option_group if multiple response categories are possible.
    civicrm_option_group Id is needed for civicrm_option_value table where we add the 'Yes' response category.
  */
  $table = 'civicrm_option_group';
  $optionGrps = [
    'Member_waiver_Agree_to_the_waiver',
    'Participant_waiver_Agree_to_the_waiver'
  ];
  $option_value = \Drupal::entityTypeManager()->getStorage('civicrm_option_value');

  foreach($optionGrps as $optionGrp) {
    $query = $db->select('civicrm_option_group','grp')
      ->fields('grp',['id'])
      ->condition('name', $optionGrp);
    $id = $query->execute()->fetch();
    $id = get_mangled_object_vars($id);
    $id = reset($id);

    // Insert 'Yes' option into civicrm_option_value table.
    $field = $option_value->create([
      'option_group_id' => $id,
      'name' => 'Yes',
      'label' => 'Yes',
      'value' => 1,
      'weight' => 1,
    ]);
    $field->enforceIsNew();
    $field->save();
  };

  // Add phone & emergency contact fields to 'Name and address' profile
  $table = 'civicrm_uf_field';

  $db->insert($table)
    ->fields([
      'uf_group_id'=> 1,
      'is_active' => 1,
      'weight' => 9,
      'field_name' => 'formatting_ec',
      'label' => 'Emergency Contact',
      'field_type' => 'Formatting',
      'help_pre' => '<span style="font-weight:bold; letter-spacing: 0.08em;">EMERGENCY CONTACT</span>',
      ])->execute();

  $db->insert($table)
    ->fields([
      'uf_group_id'=> 1,
      'is_active' => 1,
      'weight' => 10,
      'field_name' => 'custom_1',
      'label' => 'Name',
      'field_type' => 'Individual',
      ])->execute();

  $db->insert($table)
    ->fields([
      'uf_group_id'=> 1,
      'is_active' => 1,
      'weight' => 11,
      'field_name' => 'custom_2',
      'label' => 'Phone',
      'field_type' => 'Individual',
      ])->execute();

  /*************************************
    * Contribution page for membership *
    ************************************/
  $storage = \Drupal::entityTypeManager()->getStorage('civicrm_contribution_page');
  $page = $storage->create([
    'title' => 'Membership',
    'frontend_title' => 'Membership',
    'financial_type_id' => 2,
    'payment_processor' => $payId[0],
    'thankyou_title' => 'Thank you!',
    'thankyou_text' => '<p>Thank you for joining the club.</p>',
    'is_email_receipt' => 1,
    'receipt_from_name' => $site_name,
    'receipt_from_email' => $site_email,
    'receipt_text' => "Thank you for joining $site_name - Visit the website for ride information.",
    'amount_block_is_active' => 0,
    'is_share' => 0,
    'currency' => 'USD',
    'start_date' => $now,
    'created_date' => $now,
  ]);
  $page->enforceIsNew();
  $page->save();
  $pageId = $page->id();

  // Membership block.
  $mtypes[1] = NULL;
  $mtypes[2] = NULL;

  $db->insert('civicrm_membership_block')
    ->fields([
      'entity_table' => 'civicrm_contribution_page',
      'entity_id' => $pageId,
      'membership_types' => PhpSerialize::encode($mtypes),
      'display_min_fee' => 1,
      'new_title' => 'Join the club',
      'renewal_title' => 'Renew your membership',
      'is_separate_payment' => 0,
      'is_required' => 1,
      ])
    ->execute();

  // Price set, price field, price field value, price set entity (links contrib page with price set).
  $setStorage    = \Drupal::entityTypeManager()->getStorage('civicrm_price_set');
  $fieldStorage  = \Drupal::entityTypeManager()->getStorage('civicrm_price_field');
  $valueStorage  = \Drupal::entityTypeManager()->getStorage('civicrm_price_field_value');

  // Built-in IDs: Extends CiviComponent = 3 (Membership), Financial type = 2 (Member dues).
  $price_set = $setStorage->create([
    'name' => 'membership',
    'title' => 'Membership',
    'extends' => 3,
    'financial_type_id' => 2,
    'is_quick_config' => 1,
  ]);
  $price_set->enforceIsNew();
  $price_set->save();
  $priceSetId = $price_set->id();

  $db->insert('civicrm_price_set_entity')
  ->fields([
    'entity_table' => 'civicrm_contribution_page',
    'entity_id' => $pageId,
    'price_set_id' => $priceSetId,
    ])
  ->execute();

  $price_field = $fieldStorage->create([
    'price_set_id' => $priceSetId,
    'name' => 'membership_amount',
    'label' => 'Membership',
    'html_type' => 'Radio',
  ]);
  $price_field->enforceIsNew();
  $price_field->save();
  $priceFieldId = $price_field->id();

  // Insert membership types in the price_fields_values table.
  $types = getMembershipTypes();

  $i = 0;
  foreach($types as $type) {
    $price_value = $valueStorage->create([
      'price_field_id' => $priceFieldId,
      'name' => $type['name'],
      'label' => $type['name'],
      'amount' => $type['min_fee'],
      'membership_type_id' => $type['id'],
      'membership_num_terms' => 1,
      'financial_type_id' => 2,
    ]);
    $price_value->enforceIsNew();
    $price_value->save();
    $i++;
  }

  // uf_group_id = 1 is the "Name and address" profile, added to the Membership contribution page.
  $db->insert('civicrm_uf_join')
  ->fields([
    'module' => 'CiviContribute',
    'entity_table' => 'civicrm_contribution_page',
    'entity_id' => $pageId,
    'uf_group_id' => 1,
    ])
  ->execute();
}

/**
 * Get membership_types in array for filling the civicrm_price_field_value table.
 *
 * @return types
 */
function getMembershipTypes() {
  // Get max entity ID for membership types with financial type = 'Member dues'.
  $mem_types = \Drupal::entityTypeManager()->getStorage('civicrm_membership_type')
    ->loadByProperties(['financial_type_id' => '2']);

  foreach($mem_types as $mem_type) {
    $ids[] = $mem_type->id();
  }
  $max = max($ids);

  for ($i = 0; $i <= $max; $i++) {
    $mem_type = \Drupal::entityTypeManager()->getStorage('civicrm_membership_type')->load($i);
    if ($mem_type) {
      $types[] = [
        'id' => $mem_type->id(),
        'name' => $mem_type->get('name')->value,
        'min_fee' => $mem_type->get('minimum_fee')->value,
      ];
    }
  }
  return $types;
}
