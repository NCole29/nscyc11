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

use Civi\Payment\PropertyBag;
use Civi\Stripe\CheckoutOption\StripeHostedCheckout;
use CRM_Stripe_ExtensionUtil as E;

/**
 * Class CRM_Core_Payment_Stripe
 */
class CRM_Core_Payment_StripeCheckout extends CRM_Core_Payment_Stripe {

  use CRM_Core_Payment_MJWTrait;

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeName() {
    return 'stripe-checkout';
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeLabel() {
    return E::ts('Stripe Checkout');
  }

  /**
   * We can use the stripe processor on the backend
   *
   * @return bool
   */
  public function supportsBackOffice() {
    return FALSE;
  }

  /**
   * We can edit stripe recurring contributions
   * @return bool
   */
  public function supportsEditRecurringContribution() {
    return FALSE;
  }

  public function supportsRecurring() {
    return TRUE;
  }

  /**
   * Does this payment processor support refund?
   *
   * @return bool
   */
  public function supportsRefund() {
    return TRUE;
  }

  /**
   * Can we set a future recur start date?
   *
   * @return bool
   */
  public function supportsFutureRecurStartDate() {
    return FALSE;
  }

  /**
   * Is an authorize-capture flow supported.
   *
   * @return bool
   */
  protected function supportsPreApproval() {
    return FALSE;
  }

  /**
   * Does this processor support cancelling recurring contributions through code.
   *
   * If the processor returns true it must be possible to take action from within CiviCRM
   * that will result in no further payments being processed.
   *
   * @return bool
   */
  protected function supportsCancelRecurring() {
    return TRUE;
  }

  /**
   * Does the processor support the user having a choice as to whether to cancel the recurring with the processor?
   *
   * If this returns TRUE then there will be an option to send a cancellation request in the cancellation form.
   *
   * This would normally be false for processors where CiviCRM maintains the schedule.
   *
   * @return bool
   */
  protected function supportsCancelRecurringNotifyOptional() {
    return TRUE;
  }

  /**
   * Set default values when loading the (payment) form
   *
   * @param \CRM_Core_Form $form
   */
  public function buildForm(&$form) {}

  /**
   * Process payment
   * Submit a payment using Stripe's PHP API:
   * https://stripe.com/docs/api?lang=php
   * Payment processors should set payment_status_id/payment_status.
   *
   * @param array|PropertyBag $paymentParams
   *   Assoc array of input parameters for this transaction.
   * @param string $component
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doPayment(&$paymentParams, $component = 'contribute') {
    $zeroAmountPayment = $this->processZeroAmountPayment(PropertyBag::cast($paymentParams));
    if ($zeroAmountPayment) {
      return $zeroAmountPayment;
    }

    $this->getIsTestMode() ? $testConnection = $this->_paymentProcessor : $liveConnection = $this->_paymentProcessor;
    $hostedCheckoutOption = new StripeHostedCheckout($liveConnection ?? [], $testConnection ?? []);
    $successUrl = $this->getReturnSuccessUrl($paymentParams['qfKey']);
    // relative URLs cause Stripe to return an `invalid_url` error, convert to absolute URL.
    if (!str_starts_with($successUrl, 'http')) {
      $successUrl = CRM_Utils_System::url($successUrl, [], TRUE, NULL, FALSE);
    }
    $checkoutSession = $hostedCheckoutOption->createHostedCheckoutSession(
        $successUrl,
        $this->getCancelUrl($paymentParams['qfKey']),
        $this->getIsTestMode(),
        $paymentParams['contributionID'],
        // NOTE: frequency_unit is passed even if not a recurring contribution
        // so check the presence of contributionRecurID
        !empty($paymentParams['contributionRecurID']) ? $paymentParams['frequency_unit'] : NULL,
        $paymentParams['frequency_interval'] ?? 1,
      );

    $redirectUrl = $checkoutSession->url;

    // Allow each CMS to do a pre-flight check before redirecting to Stripe.
    CRM_Core_Config::singleton()->userSystem->prePostRedirect();

    if ((\CRM_Core_Config::singleton()->userFramework === 'Drupal8') && CRM_Utils_Request::retrieve('_drupal_ajax', 'Boolean', FALSE)) {
      $webformRedirect = new \Drupal\webform\Ajax\WebformRefreshCommand($redirectUrl);
      CRM_Core_Page_AJAX::returnJsonResponse([
        $webformRedirect->render(),
      ]);
      exit();
    }

    CRM_Utils_System::setHttpHeader("HTTP/1.1 303 See Other", '');
    CRM_Utils_System::redirect($redirectUrl);
  }

}
