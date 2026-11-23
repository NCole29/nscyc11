<?php

require_once 'cividiscount.civix.php';

use Civi\Api4\LineItem;
use Civi\Api4\Membership;
use Civi\CiviDiscount\Discount;
use Civi\FormHelper;
use CRM_CiviDiscount_ExtensionUtil as E;

/**
 * Implements hook_civicrm_install().
 *
 * @noinspection PhpUnused
 * @noinspection UnknownInspectionInspection
 */
function cividiscount_civicrm_install() {
  _cividiscount_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_config().
 *
 * @noinspection PhpUnused
 * @noinspection UnknownInspectionInspection
 */
function cividiscount_civicrm_config($config) {
  _cividiscount_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_enable().
 *
 * @noinspection PhpUnused
 * @noinspection UnknownInspectionInspection
 */
function cividiscount_civicrm_enable() {
  _cividiscount_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * If the event id of the form being loaded has a discount code, modify
 * the form to include the text field. Only display the text field on the
 * initial registration screen.
 *
 * Works for events and membership.
 *
 * @throws \CRM_Core_Exception
 * @noinspection PhpUnused
 * @noinspection UnknownInspectionInspection
 */
function cividiscount_civicrm_buildForm(string $formName, $form): void {
  // Skip when content is loaded via ajax, like payment processor, custom data etc
  if (((int) CRM_Utils_Request::retrieve('snippet', 'String')) === 4 || ($form->getAction() & CRM_Core_Action::DELETE)) {
    return;
  }

  // Display discount text field for offline membership/events
  if (in_array($formName, [
    'CRM_Event_Form_Participant',
    'CRM_Member_Form_Membership',
    'CRM_Member_BAO_Membership',
    'CRM_Member_Form_MembershipRenewal',
    'CRM_Event_Form_Task_Register',
  ])) {

    if ($formName === 'CRM_Event_Form_Participant' || in_array($form->getVar('_context'), ['membership', 'standalone'])) {
      if ($formName === 'CRM_Event_Form_Participant') {
        $discountCodes = CRM_CiviDiscount_BAO_Item::validDiscountsByEntity('events');
      }
      if (in_array($formName, ['CRM_Member_Form_Membership', 'CRM_Member_BAO_Membership', 'CRM_Member_Form_MembershipRenewal'])) {
        $discountCodes = CRM_CiviDiscount_BAO_Item::validDiscountsByEntity('memberships');
      }
      if ($discountCodes) {
        _cividiscount_add_discount_textfield($form);
        $code = trim((string) CRM_Utils_Request::retrieve('discountcode', 'String', $form, FALSE, '', 'REQUEST'));
        if ($code) {
          $defaults = ['discountcode' => $code];
          $form->setDefaults($defaults);
        }
      }
    }
  }
  elseif (in_array($formName, [
    'CRM_Event_Form_Registration_Register',
    //'CRM_Event_Form_Registration_AdditionalParticipant',
    'CRM_Contribute_Form_Contribution_Main',
    'CRM_Event_Form_ParticipantFeeSelection',
  ])) {

    // Display the discount text field for online events (including
    // price sets) and memberships.
    $discountedPriceOptions = $membershipTypes = [];
    $addDiscountField = FALSE;
    $formHelper = new FormHelper($form);
    if (in_array($formName, [
      'CRM_Event_Form_Registration_Register',
      //'CRM_Event_Form_Registration_AdditionalParticipant'
      'CRM_Event_Form_ParticipantFeeSelection',
    ]) && $formHelper->getPriceSetID()) {
      $contact_id = $form->getContactID();
      $addDiscountField = CRM_CiviDiscount_DiscountCalculator::isShowDiscountCodeField('Event', $formHelper->getEventID(), $formHelper->getPriceSetID(), $contact_id);
      if ($addDiscountField) {
        \Civi::resources()->addScriptFile(E::LONG_NAME, 'js/event.js', 100);
      }
    }
    elseif ($formName === 'CRM_Contribute_Form_Contribution_Main') {
      CRM_Core_Resources::singleton()
        ->addScriptFile('org.civicrm.module.cividiscount', 'js/main.js');
      $discountedPriceOptions = _cividiscount_get_discounted_membership_ids();
      if (!empty($form->_membershipBlock['membership_types'])) {
        $membershipTypes = explode(',', $form->_membershipBlock['membership_types']);
      }
      elseif (isset($form->_membershipTypeValues)) {
        $membershipTypes = array_keys($form->_membershipTypeValues);
      }
      elseif (isset($form->_priceSet)) {
        $membershipTypes = [];
        foreach ($form->_priceSet['fields'] as $field) {
          foreach ($field['options'] as $opt) {
            if ($opt['membership_type_id']) {
              $membershipTypes[] = $opt['membership_type_id'];
            }
          }
        }
      }
      if (count(array_intersect($discountedPriceOptions, $membershipTypes)) > 0) {
        $addDiscountField = TRUE;
      }
    }

    if (!$addDiscountField && $formHelper->getPriceSetID()) {
      $discountedPriceOptions = _cividiscount_get_discounted_priceset_ids();
      if (!empty($discountedPriceOptions)) {
        $availablePriceOptions = $formHelper->getPriceFieldOptionIDs();
        if (!empty(array_intersect($discountedPriceOptions, $availablePriceOptions))) {
          $addDiscountField = TRUE;
        }
      }
    }

    // Try to add the text field. If in a multi-step form, hide the text field
    // but preserve the value for later processing.
    if ($addDiscountField) {
      _cividiscount_add_discount_textfield($form);
      $code = trim((string) CRM_Utils_Request::retrieve('discountcode', 'String', $form, FALSE, '', 'REQUEST'));
      if (empty($code) && $formName === 'CRM_Event_Form_ParticipantFeeSelection') {
        $code = _cividiscount_get_item_by_track('civicrm_participant', $formHelper->getParticipantID(), $contact_id, TRUE);
      }
      if ($code) {
        $defaults = ['discountcode' => $code];
        $form->setDefaults($defaults);
      }
    }
  }
}

/**
 * Implements hook_civicrm_validateForm().
 *
 * Used in the initial event registration screen.
 *
 * @throws \CRM_Core_Exception
 */
function cividiscount_civicrm_validateForm(string $name, $fields, $files, $form, &$errors) {
  if (!in_array($name, [
    'CRM_Contribute_Form_Contribution_Main',
    'CRM_Event_Form_Participant',
    'CRM_Event_Form_Registration_Register',
    //'CRM_Event_Form_Registration_AdditionalParticipant',
    'CRM_Member_Form_Membership',
    'CRM_Member_Form_MembershipRenewal',
  ])) {
    return;
  }

  // _discountInfo is assigned in cividiscount_civicrm_buildAmount() or
  // cividiscount_civicrm_membershipTypeValues() when a discount is used.
  $discountInfo = $form->get('_discountInfo');

  $code = trim((string) CRM_Utils_Request::retrieve('discountcode', 'String', $form, FALSE, '', 'REQUEST'));

  if ((!$discountInfo || !$discountInfo['autodiscount']) && trim($code) != '') {

    if (!$discountInfo) {
      $errors['discountcode'] = CRM_CiviDiscount_DiscountCalculator::getErrorMessage($code, $form->getContactID());
      return;
    }

    $discount = $discountInfo['discount'];

    if ($discount['count_max'] > 0) {
      // Initially 1 for person registering.
      $additionalParticipants = (int) ($fields['additional_participants'] ?? $form->getSubmittedValue('additional_participants'));
      if (($discount['count_use'] + $additionalParticipants) > $discount['count_max']) {
        $errors['discountcode'] = E::ts('There are not enough uses remaining for this code.');
      }
    }
  }
}

/**
 * Implements hook_civicrm_buildAmount().
 *
 * If the event id of the form being loaded has a discount code, calculate the
 * the discount and update the price and label. Apply the initial autodiscount
 * based on a users membership.
 *
 * Check all price set items and only apply the discount to the discounted
 * items.
 *
 * @throws \CRM_Core_Exception
 */
function cividiscount_civicrm_buildAmount($pageType, &$form, &$amounts) {
  $action = $form->getAction();
  if ((!$action
      || ($action & CRM_Core_Action::PREVIEW)
      || ($action & CRM_Core_Action::ADD)
      || ($action & CRM_Core_Action::UPDATE)
      || ($action & CRM_Core_Action::VIEW)
    )
    && !empty($amounts) && is_array($amounts)) {

    $jsVars = [];
    $contact_id = $form->getContactID();
    $autodiscount = FALSE;
    $applyToAllLineItems = FALSE;
    $formHelper = new FormHelper($form);

    // Detect back-office participant form
    $isBackOfficeParticipantForm = ($form instanceof CRM_Event_Form_Participant)
      || (get_class($form) === 'CRM_Event_Form_Participant');

    $code = trim((string) CRM_Utils_Request::retrieve('discountcode', 'String', $form, FALSE, '', 'REQUEST'));
    if (!$isBackOfficeParticipantForm
      && !array_key_exists('discountcode', $form->_submitValues)
      && ($formHelper->getParticipantID())
      && ($form->getAction() & CRM_Core_Action::UPDATE)
    ) {
      $code = _cividiscount_get_item_by_track('civicrm_participant', $formHelper->getParticipantID(), $contact_id, TRUE);
    }

    $form->set('_discountInfo', NULL);
    $discountEntity = ($pageType === 'membership') ? 'MembershipType' : 'Event';
    $discountCalculator = new CRM_CiviDiscount_DiscountCalculator($discountEntity, $formHelper->getEventID(), $contact_id, $code, $formHelper->getPriceSetID());

    // Do not auto-apply discounts in back-office unless code entered
    if ($isBackOfficeParticipantForm && $code === '') {
      return;
    }

    if (empty($code)) {
      $discounts = $discountCalculator->getAvailableDiscountsWithAutodiscount();
      if (empty($discounts)) {
        return;
      }
    }
    else {
      $discounts = array_filter(
        $discountCalculator->getAvailableDiscountsWithCode(),
        fn(array $discount): bool => strcasecmp($code, $discount['code']) === 0
      );
      if (empty($discounts)) {
        $form->set('discountCodeErrorMsg', CRM_CiviDiscount_DiscountCalculator::getErrorMessage($code, $contact_id));
        return;
      }
    }

    $discountsOptions = [];
    foreach ($discounts as $discount) {
      $discountsOptions[$discount['code']] = new Discount($discount, $form->getCurrency());
    }
    // here we check if discount is configured for events or for membership types.
    // There are two scenarios:
    // 1. Discount is configure for the event or membership type, in that case we should apply discount only
    //    if default fee / membership type is configured. ( i.e price set with quick config true )
    // 2. Discount is configure at price field level, in this case discount should be applied only for
    //    that particular price set field.

    $key = array_key_first($discounts);

    // in this case discount is specified for event id or membership type id, so we need to get info of
    // associated price set fields. For events discount we already have the list, but for memberships we
    // need to filter at membership type level

    //retrieve price set field associated with this price set
    $priceSetInfo = $formHelper->getPriceFieldMetadata();
    $discountAppliedForPriceFieldValues = [];
    foreach ($discounts as $discountID => $discount) {
      $applyToAllLineItems = FALSE;
      $discountedPriceFieldValues = [];
      $autodiscount = !empty($discountCalculator->autoDiscounts) && array_key_exists($discountID, $discountCalculator->autoDiscounts);
      $priceFields = $discount['pricesets'] ?? [];
      if (empty($priceFields) && (!empty($code) || $autodiscount)) {
        // apply discount to all the price fields if no price set set
        if ($pageType === 'event') {
          $applyToAllLineItems = TRUE;
          if (!empty($key)) {
            $discounts[$key]['pricesets'] = array_keys($priceSetInfo);
          }
        }
        else {
          // filter only valid membership types that have discount
          foreach ($amounts as $priceFieldValues) {
            foreach ($priceFieldValues['options'] as $priceFieldValue) {
              if (in_array($priceFieldValue['membership_type_id'] ?? 0, $discount['memberships'] ?? [])) {
                $discountedPriceFieldValues[] = (int) $priceFieldValue['id'];
              }
            }
          }
        }
      }
      // we should check for Multiple Participant AND set Error Messages only if
      // this $discount is autodiscount or used discount
      $apcount = ($autodiscount || (!empty($code) && strcasecmp($code, $discount['code']) == 0))
        ? _cividiscount_checkEventDiscountMultipleParticipants($pageType, $form, $discount)
        : FALSE;

      if (empty($apcount)) {
        // discount is not used, so don't include it in the "best discount" candidates later
        unset($discountsOptions[$discount['code']]);

        //this was set to return but that doesn't make sense as there might be another discount
        continue;
      }

      $discountApplied = FALSE;

      if ($autodiscount || !empty($code)) {
        foreach ($amounts as $fee_id => &$fee) {
          if (!is_array($fee['options'])) {
            continue;
          }

          foreach ($fee['options'] as $option) {
            if (!empty($applyToAllLineItems)
              || in_array($option['id'], $discountedPriceFieldValues)
              || in_array($option['id'], $priceFields)) {
              $discountsOptions[$discount['code']]->addPriceOption($option);
              $discountAppliedForPriceFieldValues[] = $option['id'];
            }
          }
        }
        // If the discount code includes ALL the price_field_values that are on the form then the discount was applied to ALL lineitems.
        $applyToAllLineItems = ($discountAppliedForPriceFieldValues ?? []) == array_keys($fee['options']) && $applyToAllLineItems !== FALSE;
      }
    }

    // We now have one or more valid discounts. We want to use the one that
    // gives the greatest benefit.
    $selectedDiscount = NULL;
    $bestTotal = NULL;
    foreach ($discountsOptions as $discountsOption) {
      $total = $discountsOption->getTotal();
      if ($bestTotal === NULL || $total > $bestTotal) {
        $bestTotal = $total;
        $selectedDiscount = $discountsOption;
      }
    }
    if (empty($selectedDiscount) || empty($selectedDiscount->getOptions())) {
      return;
    }
    $appliedDiscountID = $selectedDiscount->getCode();
    $discountApplied = TRUE;
    foreach ($selectedDiscount->getOptions() as $option) {
      // This is where we actually alter the amounts in use.
      $amounts[(int) $option['price_field_id']]['options'][(int) $option['id']] = $option;
    }

    if ($discountApplied && !empty($discounts[$appliedDiscountID])) {
      if ($form->getName() !== 'Participant' && $form->getName() !== 'Main') {
        // It is doubtful this line does anything but it is tested to not
        // apply with the back office participant form & front end contribution form
        // Note that it needs to be removed or fixed to not interacted
        // with an unsupported form property.
        $ps = $form->get('priceSet');
        if (!empty($ps['fields'])) {
          $ps['fields'] = $amounts;
          $form->setVar('_priceSet', $ps);
        }
      }

      $form->set('_discountInfo', [
        'discount' => $discounts[$appliedDiscountID],
        'autodiscount' => $autodiscount,
        'contact_id' => $contact_id,
      ]);
    }
    $jsVars['discountApplied'] = $discountApplied;
    if ($discountApplied) {
      $jsVars['code'] = $code;
      $jsVars['totalAmountZero'] = FALSE;
      if ($discount['amount'] == '100' && (int) $discount['amount_type'] === 1 && !empty($applyToAllLineItems)) {
        $jsVars['totalAmountZero'] = TRUE;
      }
    }

    \Civi::resources()->addVars(E::SHORT_NAME, $jsVars);
  }

}

/**
 * We need a extra check to make sure discount is valid for additional participants
 * check the max usage and existing usage of discount code.
 *
 * @param string $pageType
 * @param CRM_Core_Form $form
 * @param array $discount
 * @return bool|int
 */
function _cividiscount_checkEventDiscountMultipleParticipants(string $pageType, &$form, $discount) {
  $apcount = 1;
  if ($pageType === 'event') {
    if ($discount['count_max'] > 0) {
      $apcount += ((int) $form->getSubmittedValue('additional_participants'));
      if (($discount['count_use'] + $apcount) > $discount['count_max']) {
        $form->set('discountCodeErrorMsg', E::ts('There are not enough uses remaining for this code.'));
        return FALSE;
      }
    }
  }
  return $apcount;
}

/**
 * Implements hook_civicrm_membershipTypeValues().
 *
 * Allow discounts to be applied to renewing memberships.
 *
 * @throws \CRM_Core_Exception
 */
function cividiscount_civicrm_membershipTypeValues($form, &$membershipTypeValues) {
  // Ignore the thank you page.
  // @todo - can this hook go now? Does buildAmount replace it?
  if ($form->getName() === 'ThankYou' || $form->getName() === 'Main') {
    return;
  }
  // @todo - we want to completely remove this hook - & just use buildAmount
  // only back office membership forms maybe use it now?

  // Only discount new or renewal memberships.
  if (!($form->getAction() & (CRM_Core_Action::ADD | CRM_Core_Action::RENEW))) {
    return;
  }
  $contact_id = $form->getContactID();
  $formHelper = new FormHelper($form);
  $form->set('_discountInfo', NULL);
  $code = CRM_Utils_Request::retrieve('discountcode', 'String', $form, FALSE, NULL, 'REQUEST');
  $discountCalculator = new CRM_CiviDiscount_DiscountCalculator('MembershipType', NULL, $contact_id, $code, $formHelper->getPriceSetID());
  if (empty($code)) {
    $discounts = $discountCalculator->getAvailableDiscountsWithAutodiscount();
    if (empty($discounts)) {
      return;
    }
    $discount = array_shift($discounts);
  }
  else {
    $discounts = array_filter(
      $discountCalculator->getAvailableDiscountsWithCode(),
      fn(array $discount): bool => strcasecmp($code, $discount['code']) === 0
    );
    if (empty($discounts)) {
      $form->set('discountCodeErrorMsg', E::ts('The discount code you entered is invalid.'));
      return;
    }
    $discount = array_shift($discounts);
  }

  if ($form instanceof \CRM_Member_Form) {
    // Force a refresh via js once loaded to update 'total_amount'
    CRM_Core_Resources::singleton()
      ->addScriptFile('org.civicrm.module.cividiscount', 'js/membership.js');
  }
  foreach ($membershipTypeValues as &$values) {
    if (!empty($discount['memberships']) && !empty($discount['memberships'][$values['id']])) {
      [$value, $label] = \CRM_CiviDiscount_DiscountCalculator::calculateDiscountAmount($discount, (float) $values['minimum_fee'], $values['name'], $form->getCurrency());
      $values['minimum_fee'] = $value;
      $values['name'] = $label;
    }
  }

  $form->set('_discountInfo', [
    'discount' => $discount,
    'contact_id' => $contact_id,
  ]);
}

/**
 * Implements hook_civicrm_postProcess().
 *
 * Record information about a discount use.
 */
function cividiscount_civicrm_postProcess($class, $form) {
  if (!in_array($class, [
    'CRM_Contribute_Form_Contribution_Confirm',
    'CRM_Event_Form_Participant',
    'CRM_Event_Form_Registration_Confirm',
    'CRM_Member_Form_Membership',
    'CRM_Member_Form_MembershipRenewal',
    'CRM_Event_Form_ParticipantFeeSelection',
  ])) {
    return;
  }
  static $done = FALSE;
  /* @var CRM_Event_Form_ParticipantFeeSelection|CRM_Member_Form_MembershipRenewal|CRM_Member_Form_Membership|CRM_Event_Form_Registration_Confirm|CRM_Event_Form_Participant|CRM_Contribute_Form_Contribution_Confirm $form */
  $discountInfo = $form->get('_discountInfo');
  if (!$discountInfo || $done) {
    return;
  }
  $done = TRUE;

  $discount = $discountInfo['discount'];
  $formHelper = new FormHelper($form);
  $discountParams = [
    'item_id' => $discount['id'],
    'contribution_id' => $formHelper->getContributionID(),
  ];

  // Online event registration.
  // Note that CRM_Event_Form_Registration_Register is an intermediate form.
  // CRM_Event_Form_Registration_Confirm completes the transaction.
  if ($class === 'CRM_Event_Form_Registration_Confirm') {
    // @todo - this attempt to get params is deprecated - these calls have been split out so they can be
    // dealt with on a form by form basis - eg. for participant form we use getParticipantValue().
    $params = $form->getVar('_params');
    $discountParams['description'] = ($params['amount_level'] ?? '') . ' ' . ($params['description'] ?? '');
    _cividiscount_consume_discount_code_for_online_event($form->getVar('_participantIDS'), $discountParams);
  }
  elseif ($class === 'CRM_Contribute_Form_Contribution_Confirm') {
    // Note that CRM_Contribute_Form_Contribution_Main is an intermediate form.
    // CRM_Contribute_Form_Contribution_Confirm completes the transaction.
    // @todo - this attempt to get params is deprecated - these calls have been split out so they can be
    // dealt with on a form by form basis - eg. for participant form we use getParticipantValue().
    $params = $form->getVar('_params');
    $discountParams['description'] = ($params['amount_level'] ?? '') . ' ' . ($params['description'] ?? '');
    _cividiscount_consume_discount_code_for_online_contribution($formHelper->getMembershipTypes(), $formHelper->getMembershipIDs(), $discountParams, $discount['memberships']);
  }
  else {
    // Offline event registration.
    if (in_array($class, ['CRM_Event_Form_Participant', 'CRM_Event_Form_ParticipantFeeSelection'])) {
      $discountParams['description'] = \Civi::format()->money($formHelper->getParticipantValue('fee_amount'));
      if (!empty($formHelper->getParticipantValue('fee_level'))) {
        $discountParams['description'] .= ' ' . implode(', ', $formHelper->getParticipantValue('fee_level'));
      }
      $discountParams['entity_id'] = $entity_id = $form->getParticipantID();
      $participant_payment = _cividiscount_get_participant_payment($entity_id);
      $discountParams['contribution_id'] = $participant_payment['contribution_id'];
      $discountParams['entity_table'] = 'civicrm_participant';
      $discountParams['contact_id'] = $formHelper->getParticipantValue('contact_id');
    }
    // Offline membership.
    else {
      // @todo check whether this uses price sets in submit & hence can use same
      // code as the online section and test whether code is decremented when a price set is used.
      $membership_types = $form->getVar('_memTypeSelected');
      $membership_type = isset($membership_types[0]) ? $membership_types[0] : NULL;

      if (!$membership_type) {
        $membership_type = $form->getVar('_memType');
      }

      // Check to make sure the discount actually applied to this membership.
      if (empty($discount['memberships'][$membership_type])) {
        return;
      }
      // @todo - this attempt to get params is deprecated - these calls have been split out so they can be
      // dealt with on a form by form basis - eg. for participant form we use getParticipantValue().
      $params = $form->getVar('_params');
      $discountParams['description'] = ($params['amount_level'] ?? '') . ' ' . ($params['description'] ?? '');
      $discountParams['entity_table'] = 'civicrm_membership';
      $discountParams['entity_id'] = $form->getVar('_id');

      $latestMembershipLineItem = LineItem::get(FALSE)
        ->addSelect('contribution_id')
        ->addWhere('entity_table', '=', 'civicrm_membership')
        ->addWhere('entity_id', '=', $discountParams['entity_id'])
        ->addOrderBy('contribution_id.receive_date', 'DESC')
        ->execute()
        ->first();
      if (!empty($latestMembershipLineItem['contribution_id'])) {
        $latestContributionID = $latestMembershipLineItem['contribution_id'];
        $discountParams['contribution_id'] = $latestContributionID;
      }

      $discountParams['contact_id'] = Membership::get(FALSE)
        ->addWhere('id', '=', $discountParams['entity_id'])
        ->execute()
        ->first()['contact_id'] ?? NULL;
    }
    civicrm_api3('DiscountTrack', 'create', $discountParams);
  }

}

/**
 * Record discount code usage from online contribution page.
 *
 * Currently (for historical reasons) only membership line items are supported
 * for discounts.
 *
 * @param $membershipTypes
 * @param $createdMembershipIDS
 * @param array $discountParams
 *   Already determined discount parameters for recording tracking code.
 * @param array $discountedMemberships
 *   Membership types eligible for discount.
 */
function _cividiscount_consume_discount_code_for_online_contribution($membershipTypes, $createdMembershipIDS, $discountParams, $discountedMemberships) {
  $discount_membership_matches = array_intersect($membershipTypes, $discountedMemberships);

  if (empty($discount_membership_matches)) {
    return;
  }

  foreach ($createdMembershipIDS as $membershipId) {
    // Check to make sure the discount actually applies to this membership type.
    $membership = _cividiscount_get_membership($membershipId);
    if (!in_array($membership['membership_type_id'], $discount_membership_matches)) {
      continue;
    }

    $discountParams['contact_id'] = $membership['contact_id'];
    $discountParams['entity_table'] = 'civicrm_membership';
    $discountParams['entity_id'] = $membershipId;

    try {
      civicrm_api3('DiscountTrack', 'create', $discountParams);
    }
    catch (CRM_Core_Exception $e) {
    }
  }
}

/**
 * Record discount code usage for online event.
 *
 * This is different to other related functions in that there can be more than 1.
 *
 * @param array $participant_ids
 * @param array $discountParams
 *
 * @throws \CRM_Core_Exception
 */
function _cividiscount_consume_discount_code_for_online_event($participant_ids, $discountParams) {
  foreach ($participant_ids as $participant_id) {
    $participant = _cividiscount_get_participant($participant_id);
    $participant_payment = _cividiscount_get_participant_payment($participant_id);
    $discountParams['contact_id'] = $participant['contact_id'];
    $discountParams['contribution_id'] = $participant_payment['contribution_id'];
    $discountParams['entity_table'] = 'civicrm_participant';
    $discountParams['entity_id'] = $participant_id;

    civicrm_api3('DiscountTrack', 'create', $discountParams);
  }
}

/**
 * For participant and member delete, decrement the code usage value since
 * they are no longer using the code.
 * When a contact is deleted, we should also delete their tracking info/usage.
 * When removing participant (and additional) from events, also delete their tracking info/usage.
 */
function cividiscount_civicrm_pre($op, $name, $id, $obj) {
  if ($op === 'delete') {
    if (in_array($name, ['Individual', 'Household', 'Organization'])) {
      $result = _cividiscount_get_item_by_track(NULL, NULL, $id);
    }
    elseif ($name === 'Participant') {
      if (($result = _cividiscount_get_participant($id)) && ($contactid = $result['contact_id'])) {
        $result = _cividiscount_get_item_by_track('civicrm_participant', $id, $contactid);
      }
    }
    elseif ($name === 'Membership') {
      if (($result = _cividiscount_get_membership($id)) && ($contactid = $result['contact_id'])) {
        $result = _cividiscount_get_item_by_track('civicrm_membership', $id, $contactid);
      }
    }
    else {
      return;
    }

    if (!empty($result)) {
      foreach ($result as $value) {
        if (!empty($value['item_id'])) {
          CRM_CiviDiscount_BAO_Item::decrementUsage($value['item_id']);
        }

        if (!empty($value['id'])) {
          CRM_CiviDiscount_BAO_Track::deleteRecord(['id' => $value['id']]);

        }
      }
    }
  }
}

/**
 * Returns all items within the field specified by 'key' for all discounts.
 */
function _cividiscount_get_items_from_discounts($discounts, $key, $include_autodiscount = FALSE) {
  $items = [];
  foreach ($discounts as $discount) {
    if ($include_autodiscount || empty($discount['autodiscount'])) {
      foreach ($discount[$key] as $v) {
        $items[$v] = is_numeric($v) ? (int) $v : $v;
      }
    }
  }

  return $items;
}

/**
 * Returns an array of all discountable priceset ids.
 */
function _cividiscount_get_discounted_priceset_ids() {
  return _cividiscount_get_items_from_discounts(CRM_CiviDiscount_BAO_Item::getValidDiscounts(), 'pricesets');
}

/**
 * Returns an array of all discountable membership ids.
 */
function _cividiscount_get_discounted_membership_ids() {
  return _cividiscount_get_items_from_discounts(CRM_CiviDiscount_BAO_Item::getValidDiscounts(), 'memberships');
}

function _cividiscount_get_item_by_track(?string $table, $eid, $cid, $returnCode = FALSE) {
  $entityTableClause = "entity_table IN ('civicrm_membership','civicrm_participant')";
  if (!empty($eid) && !empty($table)) {
    $entityTableClause = "entity_table = '{$table}' AND entity_id = {$eid}";
  }

  $sql = "SELECT cdt.id as id, item_id, code
FROM cividiscount_track cdt
LEFT JOIN cividiscount_item cdi ON cdt.item_id = cdi.id
WHERE {$entityTableClause} AND contact_id = $cid";
  $dao = CRM_Core_DAO::executeQuery($sql, []);
  $discountEntries = [];
  while ($dao->fetch()) {
    $discountEntries[] = ['id' => $dao->id, 'item_id' => $dao->item_id];
  }
  if ($returnCode) {
    return $dao->code ?? NULL;
  }
  return $discountEntries;
}

function _cividiscount_get_membership($mid = 0) {
  $result = civicrm_api3('Membership', 'get', ['membership_id' => $mid]);
  if ($result['is_error'] == 0) {
    return array_shift($result['values']);
  }

  return FALSE;
}

/**
 * @param $pid
 *
 * @return false|mixed|null
 * @throws \CRM_Core_Exception
 */
function _cividiscount_get_participant($pid = 0) {
  // v3 participant API is broken at the moment.
  // @see http://issues.civicrm.org/jira/browse/CRM-11108
  $result = civicrm_api3('Participant', 'get', ['participant_id' => $pid, 'participant_test' => 0]);
  if ($result['is_error'] == 0 && $result['count'] == 0) {
    $result = civicrm_api3('Participant', 'get', ['participant_id' => $pid, 'participant_test' => 1]);
  }
  if ($result['is_error'] == 0) {
    return array_shift($result['values']);
  }

  return FALSE;
}

/**
 * @param $pid
 *
 * @return false|mixed|null
 * @throws \CRM_Core_Exception
 */
function _cividiscount_get_participant_payment($pid = 0) {
  $result = civicrm_api3('ParticipantPayment', 'get', ['participant_id' => $pid]);
  if ($result['is_error'] == 0) {
    return array_shift($result['values']);
  }

  return FALSE;
}

/**
 * Add the discountcode field
 * @param CRM_Core_Form $form
 */
function _cividiscountAddDiscountCodeField(&$form): void {
  $discountInfo = $form->get('_discountInfo');
  $attributes['class'] = 'description';
  if (!empty($discountInfo)) {
    $attributes['discount-applied'] = 1;
  }
  $form->add(
    'text',
    'discountcode',
    E::ts('If you have a discount code, enter it here'),
    $attributes
  );
}

/**
 * Add the discount textfield to a form.
 *
 * @param CRM_Core_Form $form
 */
function _cividiscount_add_discount_textfield($form): void {
  _cividiscountAddDiscountCodeField($form);

  $errorMessage = $form->get('discountCodeErrorMsg');
  if ($errorMessage) {
    $form->setElementError('discountcode', $errorMessage);
  }
  $form->set('discountCodeErrorMsg', NULL);
  $buttonName = $form->getButtonName('reload');
  $form->addElement(
    'xbutton',
    $buttonName,
    E::ts('Apply'),
    [
      'formnovalidate' => 1,
      'type' => 'submit',
      'class' => 'crm-form-submit',
    ]
  );

  if (_cividiscount_form_is_eligible_for_pretty_placement($form)) {
    // Add the discount button immediately before the price set.
    CRM_Core_Region::instance('price-set-1')->add([
      'template' => 'CRM/CiviDiscount/discountButton.tpl',
      'weight' => -1,
      'type' => 'template',
      'name' => 'discount_code',
    ]);

    $form->assign('discountElements', [
      'discountcode',
      $buttonName,
    ]);
  }
  else {
    $bhfe = CRM_Core_Smarty::singleton()->getTemplateVars('beginHookFormElements');
    if (!$bhfe) {
      $bhfe = [];
    }
    $bhfe[] = 'discountcode';
    $bhfe[] = $buttonName;
    $form->assign('beginHookFormElements', $bhfe);
  }
}

/**
 * Can we put the discount block somewhere better than the top of the page.
 *
 * If we are in 4.6.3+ and we are working with a price set then the best place
 * to put it is in the new price-set-1 region - just before it.
 *
 * This is only tested / implemented on contribution forms at this stage.
 *
 * @param CRM_Core_Form $form
 *
 * @return bool
 *   Should we put the discount block somewhere better than just at the top.
 */
function _cividiscount_form_is_eligible_for_pretty_placement($form) {
  $formClass = get_class($form);
  if (($formClass !== 'CRM_Contribute_Form_Contribution_Main'
    && $formClass !== 'CRM_Event_Form_Registration_Register')
  ) {
    return FALSE;
  }
  return TRUE;
}

/**
 * @implements CRM_Utils_Hook::permission
 */
function cividiscount_civicrm_permission(array &$permissions): void {
  $permissions['administer CiviDiscount'] = [
    'label' => E::ts('CiviDiscount: administer CiviDiscount'),
    'description' => E::ts('Administer the CiviDiscount extension'),
    'implied_by' => ['administer CiviCRM'],
  ];
}
