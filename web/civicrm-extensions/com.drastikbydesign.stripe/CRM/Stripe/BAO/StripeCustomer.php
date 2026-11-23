<?php

use Civi\Api4\Contact;
use Civi\Api4\Extension;
use Civi\Api4\StripeCustomer;
use Civi\Payment\Exception\PaymentProcessorException;
use CRM_Stripe_ExtensionUtil as E;

class CRM_Stripe_BAO_StripeCustomer extends CRM_Stripe_DAO_StripeCustomer {

  /**
   * @param int $contactID
   * @param array $invoiceSettings
   * @param string|null $description
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function getStripeCustomerMetadata(int $contactID, array $invoiceSettings = [], ?string $description = NULL) {
    $contact = Contact::get(FALSE)
      ->addSelect('display_name', 'email_primary.email', 'email_billing.email')
      ->addWhere('id', '=', $contactID)
      ->execute()
      ->first();

    $extVersion = Extension::get(FALSE)
      ->addWhere('file', '=', E::SHORT_NAME)
      ->execute()
      ->first()['version'];

    $stripeCustomerParams = [
      'name' => $contact['display_name'],
      // Stripe does not include the Customer Name when exporting payments, just the customer
      // description, so we stick the name in the description.
      'description' => $description ?? $contact['display_name'] . ' (CiviCRM)',
      'metadata' => [
        'CiviCRM Contact ID' => $contactID,
        'CiviCRM URL' => CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contactID}", TRUE, NULL, FALSE, FALSE, TRUE),
        'CiviCRM Version' => CRM_Utils_System::version() . ' ' . $extVersion,
      ],
    ];
    $email = $contact['email_primary.email'] ?? $contact['email_billing.email'] ?? NULL;
    if ($email) {
      $stripeCustomerParams['email'] = $email;
    }

    // This is used for new subscriptions/invoices as the default payment method
    if (!empty($invoiceSettings)) {
      $stripeCustomerParams['invoice_settings'] = $invoiceSettings;
    }
    return $stripeCustomerParams;
  }

  /**
   * @param array $params
   * @param \CRM_Core_Payment_Stripe $stripe
   * @param string $stripeCustomerID
   *
   * @return string
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public static function updateMetadata(array $params, \CRM_Core_Payment_Stripe $stripe, string $stripeCustomerID): string {
    $requiredParams = ['contact_id'];
    foreach ($requiredParams as $required) {
      if (empty($params[$required])) {
        throw new PaymentProcessorException('Stripe Customer (updateMetadata): Missing required parameter: ' . $required);
      }
    }

    $stripeCustomerParams = CRM_Stripe_BAO_StripeCustomer::getStripeCustomerMetadata($params['contact_id'], $params['invoice_settings'] ?? [], $params['description'] ?? NULL);

    try {
      $stripeCustomer = $stripe->stripeClient->customers->update($stripeCustomerID, $stripeCustomerParams);
    }
    catch (Exception $e) {
      $err = $stripe->parseStripeException('create_customer', $e);
      if ($e instanceof \Stripe\Exception\PermissionException) {
        \Civi::log('stripe')->warning($stripe->getLogPrefix() . 'Could not update Stripe Customer metadata for StripeCustomerID: ' . $stripeCustomerID . '; contactID: ' . $params['contact_id']);
      }
      else {
        \Civi::log('stripe')->error($stripe->getLogPrefix() . 'Failed to create Stripe Customer: ' . $err['message'] . '; ' . print_r($err, TRUE));
        throw new PaymentProcessorException('Failed to update Stripe Customer: ' . $err['code']);
      }
    }
    return $stripeCustomer ?? '';
  }

  /**
   * Update the metadata at Stripe for a given contactID
   *
   * @param int $contactID
   *
   * @return void
   */
  public static function updateMetadataForContact(int $contactID): void {
    $customers = StripeCustomer::get(FALSE)
      ->addWhere('contact_id', '=', $contactID)
      ->execute();

    // Could be multiple customer_id's and/or stripe processors
    foreach ($customers as $customer) {
      /** @var CRM_Core_Payment_Stripe $stripe */
      StripeCustomer::updateStripe(FALSE)
        ->setPaymentProcessorID($customer['processor_id'])
        ->setContactID($contactID)
        ->setCustomerID($customer['customer_id'])
        ->execute()
        ->first();
    }
  }

}
