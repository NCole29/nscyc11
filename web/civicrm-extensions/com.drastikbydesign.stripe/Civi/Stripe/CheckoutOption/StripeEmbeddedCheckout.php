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

namespace Civi\Stripe\CheckoutOption;

use Civi\Api4\Contribution;
use Civi\Checkout\CheckoutSession;
use Civi\Core\Event\GenericHookEvent;
use Stripe\Checkout\Session;

use CRM_Stripe_ExtensionUtil as E;

/**
 * CheckoutOption for Stripe Embedded Checkout (iframed widget)
 *
 * NOTE: methods are used by afform payments and also the
 * quickform processors
 */
class StripeEmbeddedCheckout extends StripeCheckoutOptionBase {

  /**
   * @inheritdoc
   */
  public function getFrontendLabel(): string {
    return E::ts('%1 (Stripe Embedded Checkout)', [1 => $this->getConnectionDetails()['frontend_title']]);
  }

  /**
   * @inheritdoc
   */
  public function getLabel(): string {
    return E::ts('%1 (Stripe Embedded Checkout)', [1 => $this->getConnectionDetails()['title']]);
  }

  public function getAfformModule(): string {
    return 'afStripe';
  }

  /**
   * Register details for afCheckout
   *
   * @param bool $testMode
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function getAfformSettings(bool $testMode): array {
    return [
      'template' => '~/afStripe/stripe_embedded_checkout.html',
      'public_key' => $this->getPublicKey($testMode),
    ];
  }

  public function validate(GenericHookEvent $e): void {
    // no specific requirements for Stripe
  }

  /**
   * @inheritdoc
   *
   * For Stripe Embedded Checkout we initialise a Checkout Session with Stripe api,
   * then pass client secret for this session back to clientside for handling by
   * the stripe_embedded_checkout component
   */
  public function startCheckout(CheckoutSession $session): void {
    $contributionId = $session->getContributionId();
    $returnUrl = $session->getLandingUrl();
    $stripeSession = $this->createEmbeddedCheckoutSession($returnUrl, $session->isTestMode(), $contributionId);

    $session->setResponseItem('stripe_embedded_checkout', [
      'client_secret' => $stripeSession->client_secret,
      // For the client's onComplete handler, used for non-redirect payment
      // methods (eg. cards) - see createEmbeddedCheckoutSession()'s
      // redirect_on_completion setting.
      'return_url' => $returnUrl,
    ]);

    // override any default message that might interrupt the embedded widget
    $session->setResponseItem('message', FALSE);
  }

  public function continueCheckout(CheckoutSession $session): void {
    // this is passed by Stripe in the return url
    $sessionId = \CRM_Utils_Request::retrieve('session_id', 'String');

    $stripeSession = $this->getStripeClient($session->isTestMode())->checkout->sessions->retrieve($sessionId);

    // update the paymentintent tracking record (created in createCheckoutSession)
    // now that we know the outcome of the Checkout Session
    \CRM_Stripe_BAO_StripePaymentintent::writeRecord([
      'identifier' => $stripeSession->id,
      'stripe_intent_id' => $stripeSession->payment_intent ?: NULL,
      'contribution_id' => $session->getContributionId(),
      'payment_processor_id' => $this->getPaymentProcessorId($session->isTestMode()),
      'status' => $stripeSession->status,
    ]);

    // save the payment intent to the contribution trxn_id
    // for webhook matching
    if ($stripeSession->payment_intent) {
      Contribution::update(FALSE)
        ->addWhere('id', '=', $session->getContributionId())
        ->addValue('trxn_id', $stripeSession->payment_intent)
        ->execute();
    }

    switch ($stripeSession->payment_status) {
      case 'paid':
        $session
          ->success();
        // TODO: it would be nice to create payment
        // straightaway but it is not straightforward
        // we need to e.g. fetch fee amount from the
        // balance transaction associated with the charge
        // associated with the checkout...
        //
        // for consistent processing, it would be good
        // to use the same codepaths as the webhook event
        // processing. but we will need to fetch the equivalent
        // data as the webhook event passes initially
        //
        // also guard against recording payment twice
        // ->setTotalAmount($stripeSession->amount_total / 100)
        // ->setFeeAmount()
        // ->createPayment();
    }

    switch ($stripeSession->status) {
      case 'complete':
        $session->success();
        break;

      case 'expired':
        $session->fail();
        break;

      case 'open':
        $session
          ->pending()
          ->setPendingUrl($stripeSession->url);
        break;
    }
  }

  /**
   * Wrapper for createCheckoutSession with params needed for Embedded Checkout
   */
  protected function createEmbeddedCheckoutSession(string $returnUrl, bool $testMode, int $contributionId, ?string $recurIntervalUnit = NULL, int $recurIntervalCount = 1): Session {
    $uiModeParams = [
      'ui_mode' => 'embedded',
      // NOTE: embedded checkout takes a single url, regular takes two
      // the token is replaced on the Stripe side with a Stripe
      // generated value, which allows checking the checkout status
      // in continueCheckout
      'return_url' => $returnUrl . '&session_id={CHECKOUT_SESSION_ID}',
      // "if_required" only redirects for payment methods that genuinely
      // need it (eg. bank-redirect methods like Bancontact/iDEAL) - for
      // everything else (eg. cards) Stripe calls our onComplete handler
      // in-page instead, which we use to navigate back to CiviCRM
      // ourselves rather than relying solely on Stripe's own redirect,
      // which has no client-side fallback if it silently fails to fire
      // (eg. a backgrounded/suspended tab). "never" isn't usable here as
      // it would break redirect-based payment methods entirely.
      'redirect_on_completion' => 'if_required',
    ];
    return $this->createCheckoutSession($uiModeParams, $testMode, $contributionId, $recurIntervalUnit, $recurIntervalCount);
  }

}
