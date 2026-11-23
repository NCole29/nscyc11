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
use Civi\Api4\PaymentProcessor;

/**
 * @inheritDoc
 */
class Create extends BasicCreateAction {

  /**
   * The CiviCRM Payment Processor ID
   *
   * @var int
   */
  protected int $paymentProcessorID;

  /**
   * Should the webhook be disabled when created?
   *
   * @var bool
   */
  protected bool $disabled = FALSE;

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

    $params = [
      'enabled_events' => \CRM_Stripe_Webhook::getDefaultEnabledEvents(),
      'url' => \CRM_Mjwshared_Webhook::getWebhookPath($this->paymentProcessorID),
      'connect' => FALSE,
      'api_version' => \Civi\Stripe\Check::API_VERSION,
    ];
    /**
     * @var \Stripe\WebhookEndpoint $webhook
     */
    $webhook = $processor->stripeClient->webhookEndpoints->create($params);

    if ($this->disabled) {
      $updatedWebhook = $processor->stripeClient->webhookEndpoints->update($webhook->id, ['disabled' => $this->disabled]);
      // Why? We need to return the "secret", which is only available on the response from create
      $webhook->status = $updatedWebhook->status;
    }
    else {
      // Update the webhook secret on the PaymentProcessor in CiviCRM
      PaymentProcessor::update(FALSE)
        ->addValue('signature', $webhook->secret)
        ->addWhere('id', '=', $this->paymentProcessorID)
        ->execute();
    }

    $result->exchangeArray($webhook->toArray());
  }

}
