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
 * CheckoutOption for Stripe Hosted Checkout (offsite redirect)
 *
 * NOTE: methods are used by afform payments and also the
 * quickform processors
 */
class StripeHostedCheckout extends StripeCheckoutOptionBase {

  /**
   * @inheritdoc
   */
  public function getFrontendLabel(): string {
    return E::ts('%1 (Stripe Hosted Checkout)', [1 => $this->getConnectionDetails()['frontend_title']]);
  }

  /**
   * @inheritdoc
   */
  public function getLabel(): string {
    return E::ts('%1 (Stripe Hosted Checkout)', [1 => $this->getConnectionDetails()['title']]);
  }

  /**
   * Register details for afCheckout
   */
  public function getAfformSettings(bool $testMode): array {
    // nothing required clientside for hosted checkout
    return [
      'description' => E::ts('You will be redirect to Stripe to take payment details.'),
    ];
  }

  public function validate(GenericHookEvent $e): void {
    // no specific requirements for Stripe
  }

  /**
   * @inheritdoc
   *
   * For Stripe Hosted Checkout we initialise a Checkout Session with Stripe api,
   * then pass the onward url clientside to redirect
   */
  public function startCheckout(CheckoutSession $session): void {
    $contributionId = $session->getContributionId();
    $successUrl = $session->getSuccessUrl();
    $cancelUrl = $session->getCancelUrl();
    // we need to return somewhere after the hosted checkout
    // if either of the above aren't set for the session, use
    // the default Checkout landing page
    $fallbackUrl = $session->getLandingUrl();
    $stripeSession = $this->createHostedCheckoutSession($successUrl ?: $fallbackUrl, $cancelUrl ?: $fallbackUrl, $session->isTestMode(), $contributionId);

    $session->setResponseItem('redirect', $stripeSession->url);
  }

  public function continueCheckout(CheckoutSession $session): void {
    // TODO: check if the hosted page is successful
    $status = Contribution::get(FALSE)
      ->addWhere('id', '=', $session->getContributionId())
      ->addSelect('contribution_status_id:name')
      ->execute()
      ->first()['contribution_status_id:name'] ?? NULL;

    switch ($status) {
      case 'Completed':
        $session->success();
        return;

      case 'Failed':
        $session->fail();
        return;
    }
    // still pending
  }

  /**
   * Wrapper for createCheckoutSession for hosted checkout
   * NOTE: public for use in CRM_Core_Payment_StripeCheckout
   *
   * @param string $successUrl
   * @param string $cancelUrl
   * @param bool $testMode
   * @param int $contributionId
   * @param string|null $recurIntervalUnit
   * @param int $recurIntervalCount
   *
   * @return \Stripe\Checkout\Session
   * @throws \Brick\Money\Exception\UnknownCurrencyException
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function createHostedCheckoutSession(string $successUrl, string $cancelUrl, bool $testMode, int $contributionId, ?string $recurIntervalUnit = NULL, int $recurIntervalCount = 1): Session {
    // success url and cancel url are required
    $uiModeParams = [
      'ui_mode' => 'hosted',
      'success_url' => $successUrl,
      'cancel_url' => $cancelUrl,
    ];
    return $this->createCheckoutSession($uiModeParams, $testMode, $contributionId, $recurIntervalUnit, $recurIntervalCount);
  }

}
