<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

// load composer dependencies if using extension
// packaged versions
require_once 'stripe.civix.php';
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}

// backfill empty versions of CheckoutOptionInterface to prevent crash on old civicrm core
spl_autoload_register(function ($class) {
  if ($class === 'Civi\\Checkout\\CheckoutOptionInterface' && version_compare(CRM_Utils_System::version(), '6.14.alpha1', '<')) {
    interface CRM_Stripe_CheckoutOptionInterface {
    }
    class_alias('CRM_Stripe_CheckoutOptionInterface', $class);
  }
  if ($class === 'Civi\\Checkout\\AfformCheckoutOptionInterface' && version_compare(CRM_Utils_System::version(), '6.14.alpha1', '<')) {
    interface CRM_Stripe_AfformCheckoutOptionInterface {
    }
    class_alias('CRM_Stripe_AfformCheckoutOptionInterface', $class);
  }
}, TRUE, TRUE);

use CRM_Stripe_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function stripe_civicrm_config(&$config) {
  _stripe_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 */
function stripe_civicrm_install() {
  _stripe_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function stripe_civicrm_enable() {
  _stripe_civix_civicrm_enable();
}

/**
 * Add stripe.js to forms, to generate stripe token
 * hook_civicrm_alterContent is not called for all forms (eg. CRM_Contribute_Form_Contribution on backend)
 *
 * @param string $formName
 * @param \CRM_Core_Form $form
 *
 * @throws \CRM_Core_Exception
 */
function stripe_civicrm_buildForm($formName, &$form) {
  // Don't load stripe js on ajax forms
  if (CRM_Utils_Request::retrieveValue('snippet', 'String') === 'json') {
    return;
  }

  switch ($formName) {
    case 'CRM_Admin_Form_PaymentProcessor':
      $paymentProcessor = $form->getVar('_paymentProcessorDAO');
      if ($paymentProcessor && $paymentProcessor->class_name === 'Payment_Stripe') {
        // Hide configuration fields that we don't use
        foreach (['accept_credit_cards', 'url_site', 'url_recur', 'test_url_site', 'test_url_recur'] as $element) {
          if ($form->elementExists($element)) {
            $form->removeElement($element);
          }
        }
      }
      break;
  }
}

/**
 * Implements hook_civicrm_alterLogTables().
 *
 * Exclude tables from logging tables since they hold mostly temp data.
 */
function stripe_civicrm_alterLogTables(&$logTableSpec) {
  unset($logTableSpec['civicrm_stripe_paymentintent']);
}

/**
 * Implements hook_civicrm_permission().
 *
 * @see CRM_Utils_Hook::permission()
 */
function stripe_civicrm_permission(&$permissions) {
  if (\Civi::settings()->get('stripe_moto')) {
    $permissions['allow stripe moto payments'] = [
      'label' => E::ts('CiviCRM Stripe: Process MOTO transactions'),
    ];
  }
}

/**
 *
 * Implements hook_civicrm_post().
 *
 */
function stripe_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  switch ($objectName) {
    case 'Contact':
    case 'Individual':
      switch ($op) {
        case 'merge':
          try {
            CRM_Stripe_BAO_StripeCustomer::updateMetadataForContact($objectId);
          }
          catch (Exception $e) {
            \Civi::log('stripe')->error('Stripe Contact Merge failed: ' . $e->getMessage());
          }
          break;

        case 'edit':
          register_shutdown_function('stripe_civicrm_shutdown_updatestripecustomer', $objectId);
      }
      break;

    case 'Email':
      if (in_array($op, ['create', 'edit'])) {
        if ($objectRef->N == 0) {
          // Object may not be loaded; may not have contact_id available yet.
          $objectRef->find(TRUE);
        }
        if ($objectRef->contact_id) {
          register_shutdown_function('stripe_civicrm_shutdown_updatestripecustomer', $objectRef->contact_id);
        }
      }
  }
}

/**
 * Update the Stripe Customers for a contact (metadata)
 *
 * @param int $contactID
 *
 * @return void
 */
function stripe_civicrm_shutdown_updatestripecustomer(int $contactID) {
  if (isset(\Civi::$statics['stripe_civicrm_shutdown_updatestripecustomer'][$contactID])) {
    // Don't run the update more than once
    return;
  }
  \Civi::$statics['stripe_civicrm_shutdown_updatestripecustomer'][$contactID] = TRUE;

  try {
    // Does the contact have a Stripe customer record?
    $stripeCustomers = \Civi\Api4\StripeCustomer::get(FALSE)
      ->addWhere('contact_id', '=', $contactID)
      ->execute();
    // Update the contact details at Stripe for each customer associated with this contact
    foreach ($stripeCustomers as $stripeCustomer) {
      \Civi\Api4\StripeCustomer::updateStripe(FALSE)
        ->setPaymentProcessorID($stripeCustomer['processor_id'])
        ->setContactID($stripeCustomer['contact_id'])
        ->setCustomerID($stripeCustomer['customer_id'])
        ->execute();
    }
  }
  catch (Exception $e) {
    \Civi::log('stripe')->error(
      'Stripe Contact update failed: ' . $e->getMessage(),
      ['contact_id' => $contactID]
    );
  }

}
