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

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @inheritDoc
 */
class GetFromStripe extends AbstractAction {

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

    try {
      $webhooks = $processor->stripeClient->webhookEndpoints->all(['limit' => 100]);
    }
    catch (\Throwable $e) {
      throw new \CRM_Core_Exception('Unable to retrieve webhook endpoints. ' . $e->getMessage());
    }

    $result->exchangeArray($webhooks->data ?? []);
  }

}
