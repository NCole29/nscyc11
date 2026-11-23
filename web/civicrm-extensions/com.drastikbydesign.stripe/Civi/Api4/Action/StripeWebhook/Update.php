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
use Civi\Api4\StripeWebhook;

/**
 * @inheritDoc
 */
class Update extends BasicCreateAction {

  /**
   * The CiviCRM Payment Processor ID
   *
   * @var int
   */
  protected int $paymentProcessorID;

  /**
   * Optional pre-fetched list of this processor's webhook endpoints (eg. as
   * already retrieved by CRM_Stripe_Webhook::check()), to avoid an extra
   * round-trip to Stripe when the caller already has it. If not provided,
   * it will be fetched.
   *
   * @var array|null
   */
  protected ?array $webhooks = NULL;

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

    $webhooks = $this->webhooks ?? iterator_to_array(
      StripeWebhook::getFromStripe(FALSE)
        ->setPaymentProcessorID($this->paymentProcessorID)
        ->execute()
    );

    $webhookPath = \CRM_Mjwshared_Webhook::getWebhookPath($this->paymentProcessorID);
    $processorWebhooks = array_filter(
      $webhooks,
      fn ($webhook) => str_starts_with($webhook->url, $webhookPath)
    );
    $classified = \CRM_Stripe_Webhook::classifyWebhooksForProcessor($processorWebhooks);

    if ($classified['keep'] === NULL) {
      // Need to create a new webhook
      $currentWebhook = StripeWebhook::create(FALSE)
        ->setPaymentProcessorID($this->paymentProcessorID)
        ->setDisabled(TRUE)
        ->execute();
    }
    else {
      $currentWebhook = $classified['keep']->toArray();
    }
    if (($classified['disable'] !== NULL) && ($classified['disable']->status !== 'disabled')) {
      $processor->stripeClient->webhookEndpoints->update($classified['disable']->id, ['disabled' => TRUE]);
    }
    foreach ($classified['delete'] as $webhook) {
      $processor->stripeClient->webhookEndpoints->delete($webhook->id);
    }

    if ($currentWebhook['status'] === 'disabled') {
      $updatedWebhook = $processor->stripeClient->webhookEndpoints->update($currentWebhook['id'], ['disabled' => FALSE]);
      $currentWebhook['status'] = $updatedWebhook->status;
    }

    if (isset($currentWebhook['secret'])) {
      // Update the webhook secret on the PaymentProcessor in CiviCRM
      // The secret will only be provided when the webhook is first created
      PaymentProcessor::update(FALSE)
        ->addValue('signature', $currentWebhook['secret'])
        ->addWhere('id', '=', $this->paymentProcessorID)
        ->execute();
    }

    unset($currentWebhook['secret']);

    $result->exchangeArray($currentWebhook);
  }

}
