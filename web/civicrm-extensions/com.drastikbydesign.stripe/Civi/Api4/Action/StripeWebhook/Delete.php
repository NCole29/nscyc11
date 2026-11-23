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

namespace Civi\Api4\Action\StripeWebhook;

use Civi\Api4\Generic\BasicCreateAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\StripeWebhook;

/**
 * @inheritDoc
 */
class Delete extends BasicCreateAction {

  /**
   * The CiviCRM Payment Processor ID
   *
   * @var int
   */
  protected int $paymentProcessorID;

  /**
   * @param \Civi\Api4\Generic\Result $result
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function _run(Result $result) {
    $processor = \Civi\Payment\System::singleton()->getById($this->paymentProcessorID);

    if (!($processor instanceof \CRM_Core_Payment_Stripe)) {
      throw new \CRM_Core_Exception('Not a Stripe Payment Processor.');
    }
    if ($processor->stripeClient === NULL) {
      // This means we only configured live OR test and not both.
      throw new \CRM_Core_Exception('Payment Processor is not configured.');
    }

    $webhooks = StripeWebhook::getFromStripe(FALSE)
      ->setPaymentProcessorID($this->paymentProcessorID)
      ->execute();

    foreach ($webhooks as $webhook) {
      if (str_starts_with($webhook->url, \CRM_Mjwshared_Webhook::getWebhookPath($this->paymentProcessorID))) {
        // This webhook is for this payment processor
        if ($webhook->status === 'disabled') {
          // This is a disabled webhook for our paymentprocessor - delete it
          $webhooksToDelete[] = $webhook;
        }
      }
    }

    if (!empty($webhooksToDelete)) {
      foreach ($webhooksToDelete as $webhook) {
        $processor->stripeClient->webhookEndpoints->delete($webhook->id);
      }
    }

    $result->exchangeArray($webhooksToDelete ?? []);
  }

}
