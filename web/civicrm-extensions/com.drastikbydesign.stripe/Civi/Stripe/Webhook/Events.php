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

namespace Civi\Stripe\Webhook;

use Brick\Money\Money;
use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\StripePaymentintent;
use CRM_Stripe_ExtensionUtil as E;
use Psr\Log\LogLevel;

class Events {

  use \CRM_Core_Payment_MJWIPNTrait;

  /**
   * Lock name prefix for the per-contribution locks that guard the
   * read-check-then-write sections of the event handlers.
   */
  private const CONTRIBUTION_LOCK_PREFIX = 'data.contribute.contribution.';

  /**
   * Seconds to wait for a contended contribution lock before giving up and
   * leaving the event for a later run.
   *
   * This matches \CRM_Core_Lock::TIMEOUT, which is what the lock would use
   * anyway, but state it here so that changing it is a deliberate act.
   */
  private const CONTRIBUTION_LOCK_TIMEOUT = 3;

  /**
   * @var \Civi\Stripe\Api
   */
  private $api;

  public function __construct(int $paymentProcessorID) {
    $this->setPaymentProcessor($paymentProcessorID);
    $this->api = new \Civi\Stripe\Api($this->_paymentProcessor);
  }

  /**
   * @param string $eventID
   *
   * @return void
   */
  public function setEventID(string $eventID): void {
    $this->eventID = $eventID;
  }

  /**
   * @param string $eventType
   *
   * @return void
   */
  public function setEventType(string $eventType): void {
    $this->eventType = $eventType;
  }

  /**
   * @param \Stripe\StripeObject $data
   *
   * @return void
   */
  public function setData($data): void {
    $this->data = $data;
  }

  /**
   * @return \stdClass
   */
  public function getResultObject() {
    $return = new \stdClass();
    $return->message = '';
    $return->ok = FALSE;
    $return->exception = NULL;
    // Neither processed nor failed: the event should be attempted again.
    $return->retry = FALSE;
    return $return;
  }

  /**
   * Acquire the lock guarding writes to a contribution, or flag the event to
   * be attempted again later.
   *
   * A failed acquire means the lock was held throughout
   * self::CONTRIBUTION_LOCK_TIMEOUT seconds, so another process is writing to
   * this contribution right now. Continuing without the lock is what produces
   * duplicate payments and deadlocks, so the caller must return instead.
   *
   * @param string $function The calling handler, for the result message.
   * @param string $identifier Contribution ID, or Stripe invoice ID where the
   *   contribution is not yet known.
   * @param \stdClass $return Flagged for retry if the lock is contended.
   *
   * @return \Civi\Core\Lock\LockInterface|null NULL if the lock was not acquired.
   */
  private function acquireContributionLock(string $function, string $identifier, \stdClass $return): ?\Civi\Core\Lock\LockInterface {
    $lock = \Civi::lockManager()->acquire(self::CONTRIBUTION_LOCK_PREFIX . $identifier, self::CONTRIBUTION_LOCK_TIMEOUT);
    if (!$lock->isAcquired()) {
      $return->retry = TRUE;
      $return->message = $this->formatResultMessage(
        $function,
        E::ts('Could not acquire lock to record %1. Leaving event for a later run.', [1 => $this->getEventType()]),
        ['identifier' => $identifier]
      );
      // Contention is expected under concurrency and is now handled by
      // retrying, so this is not an error unless we later give up on it.
      \Civi::log('stripe')->warning($return->message);
      return NULL;
    }
    return $lock;
  }

  /**
   * A) A one-off contribution will have trxn_id == stripe.charge_id
   * B) A contribution linked to a recur (stripe subscription):
   *   1. May have the trxn_id == stripe.subscription_id if the invoice was not generated at the time the contribution
   * was created
   *     (Eg. the recur was setup with a future recurring start date).
   *     This will be updated to trxn_id == stripe.invoice_id when a suitable IPN is received
   * @todo: Which IPN events will update this?
   *   2. May have the trxn_id == stripe.invoice_id if the invoice was generated at the time the contribution was
   *   created OR the contribution has been updated by the IPN when the invoice was generated.
   *
   * @param string $chargeID Optional, one of chargeID, invoiceID, subscriptionID must be specified
   * @param string $invoiceID
   * @param string $subscriptionID
   * @param string $paymentIntentID
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  private function findContribution(string $chargeID = '', string $invoiceID = '', string $subscriptionID = '', string $paymentIntentID = ''): array {
    $paymentParams = [
      'contribution_test' => $this->getPaymentProcessor()->getIsTestMode(),
    ];

    // A0) Resolve via our own per-attempt tracking row first. Contribution.trxn_id
    // gets overwritten by every checkout attempt on the same Contribution (eg. a
    // retried payment) - if this Contribution was retried after this specific
    // payment succeeded, trxn_id may no longer point back to it even though this
    // payment belongs to it. The paymentintent row is keyed by this attempt's own
    // stripe_intent_id and never gets touched by a later attempt.
    if (!empty($paymentIntentID)) {
      $paymentintentRow = StripePaymentintent::get(FALSE)
        ->addSelect('contribution_id')
        ->addWhere('stripe_intent_id', '=', $paymentIntentID)
        ->addWhere('contribution_id', 'IS NOT EMPTY')
        ->execute()
        ->first();
      if (!empty($paymentintentRow['contribution_id'])) {
        $contribution = Contribution::get(FALSE)
          ->addWhere('id', '=', $paymentintentRow['contribution_id'])
          ->addWhere('is_test', '=', $paymentParams['contribution_test'])
          ->execute()
          ->first();
        if (!empty($contribution)) {
          return $contribution;
        }
      }
    }

    // A) One-off contribution
    if (!empty($chargeID)) {
      $paymentParams['trxn_id'] = $chargeID;
      $contributionApi3 = civicrm_api3('Mjwpayment', 'get_contribution', $paymentParams);
    }

    // A2) trxn_id = paymentIntentID if set by checkout.session.completed
    if (empty($contributionApi3['count'])) {
      if (!empty($paymentIntentID)) {
        $paymentParams['trxn_id'] = $paymentIntentID;
        $contributionApi3 = civicrm_api3('Mjwpayment', 'get_contribution', $paymentParams);
      }
    }

    // B2) Contribution linked to subscription and we have invoice_id
    // @todo there is a case where $contribution is not defined (i.e. if charge_id is empty)
    if (empty($contributionApi3['count'])) {
      unset($paymentParams['trxn_id']);
      if (!empty($invoiceID)) {
        $paymentParams['order_reference'] = $invoiceID;
        $contributionApi3 = civicrm_api3('Mjwpayment', 'get_contribution', $paymentParams);
      }
    }

    // B1) Contribution linked to subscription and we have subscription_id
    // @todo there is a case where $contribution is not defined (i.e. if charge_id, invoice_id are empty)
    if (empty($contributionApi3['count'])) {
      unset($paymentParams['trxn_id']);
      if (!empty($subscriptionID)) {
        $paymentParams['order_reference'] = $subscriptionID;
        $contributionApi3 = civicrm_api3('Mjwpayment', 'get_contribution', $paymentParams);
      }
    }

    // @todo there is a case where $contribution is not defined (i.e. if charge_id, invoice_id, subscription_id are empty)
    if (empty($contributionApi3['count'])) {
      if ((bool) \Civi::settings()->get('stripe_ipndebug')) {
        $message = $this->getPaymentProcessor()->getPaymentProcessorLabel() . 'No matching contributions for event ' . $this->getEventID();
        \Civi::log('stripe')->debug($message);
      }
      $result = [];
      \CRM_Mjwshared_Hook::webhookEventNotMatched('stripe', $this, 'contribution_not_found', $result);
      if (empty($result['contribution'])) {
        return [];
      }
      $contribution = $result['contribution'];
    }
    else {
      $contribution = $contributionApi3['values'][$contributionApi3['id']];
    }
    return $contribution ?? [];
  }

  /**
   * Find the Pending Contribution created alongside a ContributionRecur at form
   * submit, for the first invoice of a subscription to adopt.
   *
   * Under Stripe Checkout nothing writes Contribution.trxn_id until
   * checkout.session.completed is processed, so findContribution() cannot match
   * it and doInvoicePaid would create a duplicate instead.
   *
   * Scoped by Pending status, not by an empty trxn_id: checkout.session.completed
   * can stamp trxn_id within the same second, and a paid Contribution is never
   * Pending, so this cannot claim settled money.
   *
   * @param array $contributionRecur
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  private function findInitialContributionForRecur(array $contributionRecur): array {
    if (empty($contributionRecur['id'])) {
      return [];
    }

    $contribution = Contribution::get(FALSE)
      ->addWhere('contribution_recur_id', '=', $contributionRecur['id'])
      ->addWhere('contribution_status_id:name', '=', 'Pending')
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->addOrderBy('id', 'ASC')
      ->execute()
      ->first() ?? [];

    if (empty($contribution)) {
      // The caller will now create a Contribution - the path that produces
      // duplicates on a first invoice.
      if ((bool) \Civi::settings()->get('stripe_ipndebug')) {
        \Civi::log('stripe')->debug($this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID()
          . ': No Pending Contribution on ContributionRecur ' . $contributionRecur['id']
          . ' to adopt for the first invoice of this subscription; a new one will be created');
      }
      return [];
    }

    // Logged at info, not debug: the payment lands on a Contribution created at
    // form submit rather than one created here.
    \Civi::log('stripe')->info($this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID()
      . ': Recording payment against the existing Contribution ' . $contribution['id']
      . ' on ContributionRecur ' . $contributionRecur['id']
      . ' (first invoice of the subscription) rather than creating a new one');

    return $contribution;
  }

  /**
   * Is this the first invoice Stripe generated for a subscription?
   *
   * If so, the Contribution created at form submit is the one this invoice
   * pays for, rather than a new instalment in the series.
   *
   * @param \Stripe\StripeObject $stripeInvoice
   *
   * @return bool
   */
  private function isFirstInvoiceForSubscription($stripeInvoice): bool {
    return $this->api->getValueFromStripeObject('billing_reason', 'String', $stripeInvoice)
      === \Stripe\Invoice::BILLING_REASON_SUBSCRIPTION_CREATE;
  }

  /**
   * This allows us to end a subscription once:
   *   a) We've reached the end date / number of installments
   *   b) The recurring contribution is marked as completed
   *
   * @param string $subscriptionID
   * @param int|NULL $contributionRecurID
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  private function handleInstallmentsForSubscription(string $subscriptionID = '', ?int $contributionRecurID = NULL) {
    // Check that we have both contributionRecurID and subscriptionID
    if ((empty($contributionRecurID)) || (empty($subscriptionID))) {
      return;
    }

    $contributionRecur = ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $contributionRecurID)
      ->execute()
      ->first();

    // Do we have an end date?
    if (empty($contributionRecur['end_date'])) {
      return;
    }

    // There is no easy way of retrieving a count of all invoices for a subscription so we ignore the "installments"
    //   parameter for now and rely on checking end_date (which was calculated based on number of installments...)
    // if (empty($contributionRecur['installments'])) { return; }

    $stripeSubscription = $this->getPaymentProcessor()->stripeClient->subscriptions->retrieve($subscriptionID);

    // If the subscription is already cancelled don't try to modify it
    if (!empty($stripeSubscription->canceled_at) || $stripeSubscription->status === 'canceled') {
      return;
    }

    // If we've passed the end date cancel the subscription
    if (($stripeSubscription->current_period_end >= strtotime($contributionRecur['end_date']))
      || ($contributionRecur['contribution_status_id']
        == \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'Completed'))) {
      $this->getPaymentProcessor()->stripeClient->subscriptions->update($subscriptionID, ['cancel_at_period_end' => TRUE]);
      $this->updateRecurCompleted(['id' => $contributionRecurID]);
    }
  }

  /**
   * Get the recurring contribution from the Stripe subscription ID
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function getRecurFromSubscriptionID($subscriptionID): array {
    if (empty($subscriptionID)) {
      return [];
    }

    // Get the recurring contribution record associated with the Stripe subscription.
    $contributionRecur = ContributionRecur::get(FALSE)
      ->addWhere('processor_id', '=', $subscriptionID)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute()
      ->first();
    if (empty($contributionRecur)) {
      if ((bool) \Civi::settings()->get('stripe_ipndebug')) {
        $message = $this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID() . ': Cannot find recurring contribution for subscription ID: ' . $subscriptionID;
        \Civi::log('stripe')->debug($message);
      }
      return [];
    }

    return $contributionRecur;
  }

  /**
   * Get the recurring contribution for a Stripe subscription, falling back to
   * the event payload when the subscription ID alone does not resolve.
   *
   * processor_id is only written when checkout.session.completed is processed,
   * and Stripe gives no ordering guarantee - invoice.paid frequently arrives
   * first, matches nothing, and the contribution stays Pending.
   *
   * The fallback reads the recur ID from the subscription metadata written at
   * Checkout Session creation. It is already in the delivered payload, so this
   * costs no extra Stripe API call.
   *
   * @param string $subscriptionID
   * @param \Stripe\StripeObject $stripeObject
   *   The event's data object: a Stripe Invoice or a Stripe Subscription.
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public function getRecurFromStripeObject($subscriptionID, $stripeObject): array {
    $contributionRecur = $this->getRecurFromSubscriptionID($subscriptionID);
    if (!empty($contributionRecur)) {
      return $contributionRecur;
    }

    $contributionRecurID = $this->api->getValueFromStripeObject('subscription_metadata_recur_id', 'Int', $stripeObject);
    if (empty($contributionRecurID) || empty($subscriptionID)) {
      // Logged so that "metadata absent" is distinguishable from "fallback
      // never ran".
      if ((bool) \Civi::settings()->get('stripe_ipndebug')) {
        \Civi::log('stripe')->debug($this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID()
          . ': No ' . \CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID . ' in subscription metadata for subscription '
          . var_export($subscriptionID, TRUE) . ' (object type: ' . var_export($stripeObject->object ?? NULL, TRUE) . ')');
      }
      return [];
    }

    $contributionRecur = ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $contributionRecurID)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute()
      ->first();
    if (empty($contributionRecur)) {
      \Civi::log('stripe')->warning($this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID()
        . ': Subscription metadata names ContributionRecur ' . $contributionRecurID . ' but no such record exists');
      return [];
    }

    // A non-empty processor_id does not mean the recur is already bound to a
    // subscription: core seeds it with a placeholder hash at form submit. Only
    // refuse when it holds a *different* sub_... id, where re-pointing would
    // rebind someone's recurring payment to the wrong subscription.
    $existingProcessorID = (string) ($contributionRecur['processor_id'] ?? '');
    if (str_starts_with($existingProcessorID, 'sub_') && ($existingProcessorID !== $subscriptionID)) {
      \Civi::log('stripe')->warning($this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID()
        . ': Subscription metadata names ContributionRecur ' . $contributionRecurID . ' but that is already linked to subscription '
        . $existingProcessorID . ', not ' . $subscriptionID . '. Ignored');
      return [];
    }

    if ($existingProcessorID !== $subscriptionID) {
      // Backfill, so every later event for this subscription resolves via the
      // normal processor_id lookup and this fallback runs at most once.
      ContributionRecur::update(FALSE)
        ->addWhere('id', '=', $contributionRecur['id'])
        ->addValue('processor_id', $subscriptionID)
        ->execute();
      $contributionRecur['processor_id'] = $subscriptionID;
      \Civi::log('stripe')->info($this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID()
        . ': Resolved ContributionRecur ' . $contributionRecur['id'] . ' from subscription metadata and set processor_id to '
        . $subscriptionID . ' (was ' . var_export($existingProcessorID, TRUE) . ')');
    }

    return $contributionRecur;
  }

  /**
   * Create the next contribution for a recurring contribution
   * This happens when Stripe generates a new invoice and notifies us (normally by invoice.finalized but
   * invoice.payment_succeeded sometimes arrives first).
   *
   * @param string $chargeID
   * @param string $invoiceID
   * @param array $contributionRecur
   *
   * @return int
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function createNextContributionForRecur(string $chargeID, string $invoiceID, array $contributionRecur): int {
    // We have a recurring contribution but no contribution so we'll repeattransaction
    // Stripe has generated a new invoice (next payment in a subscription) so we
    //   create a new contribution in CiviCRM
    $balanceTransactionDetails = empty($chargeID) ? [] : $this->api->getDetailsFromBalanceTransactionByChargeID($chargeID);
    $repeatContributionParams = [
      'contribution_recur_id' => $contributionRecur['id'],
      'contribution_status_id' => \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending'),
      'receive_date' => $this->api->getValueFromStripeObject('receive_date', 'String', $this->getData()->object),
      'order_reference' => $invoiceID,
      'trxn_id' => $chargeID,
      'total_amount' => $this->api->getValueFromStripeObject('amount', 'String', $this->getData()->object),
      // 'fee_amount' Added below via $balanceTransactionDetails
    ];
    foreach ($balanceTransactionDetails as $key => $value) {
      $repeatContributionParams[$key] = $value;
    }
    return $this->repeatContribution($repeatContributionParams);
    // Don't touch the contributionRecur as it's updated automatically by Contribution.repeattransaction
  }

  /**
   * Format the message that is returned from the event processor
   *
   * @param string $function
   * @param string $message
   * @param array $entityIDs
   *
   * @return string
   */
  private function formatResultMessage(string $function, string $message, array $entityIDs = []): string {
    $resultMessage = $function . ': ' . $message;
    if (!empty($entityIDs)) {
      $entityStrings = [];
      foreach ($entityIDs as $entityName => $entityID) {
        $entityStrings[] = $entityName . ':' . $entityID;
      }
      $resultMessage = $resultMessage . '. ' . implode(';', $entityStrings);
    }

    return $resultMessage;
  }

  /**
   * Webhook event: charge.succeeded / charge.captured
   * We process charge.succeeded per charge.captured
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function doChargeSucceeded(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'charge') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    // For a recurring contribution we can process charge.succeeded once we receive the event with an invoice ID.
    // For a single contribution we can't process charge.succeeded because it only triggers BEFORE the charge is captured
    if (empty($this->api->getValueFromStripeObject('customer_id', 'String', $this->getData()->object))) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'not processing because no customer_id');
      $return->ok = TRUE;
      return $return;
    }

    $chargeID = $this->api->getValueFromStripeObject('charge_id', 'String', $this->getData()->object);
    if (!$chargeID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing charge_id');
      return $return;
    }
    $paymentIntentID = $this->api->getValueFromStripeObject('payment_intent_id', 'String', $this->getData()->object);
    $invoiceID = $this->api->getValueFromStripeObject('invoice_id', 'String', $this->getData()->object);

    $contribution = $this->findContribution($chargeID, $invoiceID, '', $paymentIntentID);
    if (empty($contribution)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'ignoring - contribution not found');
      $return->ok = TRUE;
      return $return;
    }

    // For a single contribution we have to use charge.captured because it has the customer_id.

    // We only process charge.captured for one-off contributions (see invoice.paid/invoice.payment_succeeded for recurring)
    if (!empty($contribution['contribution_recur_id'])) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'ignoring - contribution has recur');
      $return->ok = TRUE;
      return $return;
    }

    // We only process charge.captured for one-off contributions
    if (empty($this->api->getValueFromStripeObject('captured', 'Boolean', $this->getData()->object))) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'ignoring - charge not captured');
      $return->ok = TRUE;
      return $return;
    }

    $pendingContributionStatusID = (int) \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $failedContributionStatusID = (int) \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed');
    $completedContributionStatusID = (int) \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $statusesAllowedToComplete = [$pendingContributionStatusID, $failedContributionStatusID];

    // If contribution is in Pending or Failed state record payment and transition to Completed
    if (in_array($contribution['contribution_status_id'], $statusesAllowedToComplete)) {
      $balanceTransactionDetails = $this->api->getDetailsFromBalanceTransactionByChargeObject($this->getData()->object);
      $contributionParams = [
        'contribution_id' => $contribution['id'],
        'trxn_date' => $this->api->getValueFromStripeObject('receive_date', 'String', $this->getData()->object),
        'order_reference' => !empty($invoiceID) ? $invoiceID : $chargeID,
        'trxn_id' => $chargeID,
        'total_amount' => $this->api->getValueFromStripeObject('amount', 'Float', $this->getData()->object),
        // 'fee_amount' Added below via $balanceTransactionDetails
        // We pass this through in case we changed it to a Stripe specific method (eg. in doCheckoutSessionCompleted)
        'payment_instrument_id' => $contribution['payment_instrument_id'],
      ];
      foreach ($balanceTransactionDetails as $key => $value) {
        $contributionParams[$key] = $value;
      }

      $this->updateContributionCompleted($contributionParams);
    }
    elseif ($contribution['contribution_status_id'] === $completedContributionStatusID) {
      // By this point we should have a contribution and a completed payment
      $financialTrxn = \Civi\Api4\FinancialTrxn::get(FALSE)
        ->addSelect('*', 'custom.*')
        ->addWhere('trxn_id', '=', $chargeID)
        ->addWhere('is_payment', '=', TRUE)
        ->addWhere('status_id:name', '=', 'Completed')
        ->execute()
        ->first();

      if (empty($financialTrxn)) {
        // This charge succeeded at Stripe but was never recorded here - eg. a
        // retried Checkout Session whose earlier attempt's delayed webhook
        // arrives after a later attempt already completed the Contribution.
        // Both payments genuinely happened, so record this one too rather
        // than silently dropping it.
        $balanceTransactionDetails = $this->api->getDetailsFromBalanceTransactionByChargeObject($this->getData()->object);
        $additionalPaymentParams = [
          'contribution_id' => $contribution['id'],
          'trxn_date' => $this->api->getValueFromStripeObject('receive_date', 'String', $this->getData()->object),
          'order_reference' => !empty($invoiceID) ? $invoiceID : $chargeID,
          'trxn_id' => $chargeID,
          'total_amount' => $this->api->getValueFromStripeObject('amount', 'Float', $this->getData()->object),
          'payment_instrument_id' => $contribution['payment_instrument_id'],
        ];
        // fee_amount is a real FinancialTrxn field; the rest of the balance
        // transaction detail (available_on, exchange_rate, payout_*, ...) is
        // only tracked via the Payment_details custom group.
        if (isset($balanceTransactionDetails['fee_amount'])) {
          $additionalPaymentParams['fee_amount'] = $balanceTransactionDetails['fee_amount'];
          unset($balanceTransactionDetails['fee_amount']);
        }
        foreach ($balanceTransactionDetails as $key => $value) {
          $additionalPaymentParams["Payment_details.{$key}"] = $value;
        }
        $this->recordAdditionalPayment($additionalPaymentParams);
        \Civi::log('stripe')->critical($this->formatResultMessage(__FUNCTION__, 'recorded additional successful payment on already-completed contribution - check for a duplicate/unrefunded charge', ['coid' => $contribution['id']]));
        $return->message = $this->formatResultMessage(__FUNCTION__, 'already completed - recorded additional payment', ['coid' => $contribution['id']]);
        $return->ok = TRUE;
        return $return;
      }

      $return->message = $this->formatResultMessage(__FUNCTION__, 'already completed. No additional payment details added', ['coid' => $contribution['id']]);
      if (empty($financialTrxn['Payment_details.available_on'])) {
        $balanceTransactionDetails = $this->api->getDetailsFromBalanceTransactionByChargeObject($this->getData()->object);
        foreach ($balanceTransactionDetails as $key => $value) {
          $paymentParams[$key] = $value;
        }

        $customFields = \Civi\Api4\CustomField::get(FALSE)
          ->addWhere('custom_group_id:name', '=', 'Payment_details')
          ->execute()
          ->indexBy('name');
        foreach ($customFields as $key => $value) {
          if (isset($paymentParams[$key])) {
            $customParams['custom_' . $value['id']] = $paymentParams[$key];
          }
        }
        if (!empty($customParams)) {
          $customParams['entity_id'] = $financialTrxn['id'];
          civicrm_api3('CustomValue', 'create', $customParams);
          $return->message = $this->formatResultMessage(__FUNCTION__, 'already completed. Added additional payment details', ['coid' => $contribution['id']]);
        }
      }
      $return->ok = TRUE;
      return $return;
    }

    $return->message = $this->formatResultMessage(__FUNCTION__, 'OK', ['coid' => $contribution['id']]);
    $return->ok = TRUE;
    return $return;
  }

  /**
   * Record a successful payment that arrives after its Contribution has
   * already been completed by a different attempt - eg. a retried Checkout
   * Session whose earlier attempt's webhook was delayed until after a later
   * attempt had already completed the order. Both payments genuinely
   * happened, so this is recorded on the same Contribution rather than
   * silently dropped, the same way a split/partial payment is.
   *
   * @param array $params
   *
   * @throws \CRM_Core_Exception
   */
  private function recordAdditionalPayment(array $params): void {
    $this->checkRequiredParams('recordAdditionalPayment', ['contribution_id', 'total_amount', 'trxn_id'], $params);
    $params['payment_processor_id'] = $this->getPaymentProcessor()->getID();

    // Use mjwshared's Mjwpayment.create_payment rather than API4 Payment::create() -
    // the latter silently drops Payment_details.* custom fields on any CiviCRM below
    // the fix in https://github.com/civicrm/civicrm-core/pull/36604. Switch to API4
    // directly once that fix is in the minimum supported core version.
    // Mjwpayment.create_payment takes bare custom field names, not the Payment_details.
    // prefixed form used elsewhere in this class.
    $mjwPaymentParams = [];
    foreach ($params as $key => $value) {
      $mjwPaymentParams[str_starts_with($key, 'Payment_details.') ? substr($key, strlen('Payment_details.')) : $key] = $value;
    }

    $lock = \Civi::lockManager()->acquire('data.contribute.contribution.' . $params['contribution_id']);
    if (!$lock->isAcquired()) {
      throw new \Civi\Payment\Exception\PaymentProcessorException('Could not acquire lock to record additional payment for contribution: ' . $params['contribution_id']);
    }
    // Check if it was already recorded (in case two processes are running at the same time).
    $alreadyRecorded = \Civi\Api4\Payment::get(FALSE)
      ->addWhere('contribution_id', '=', $params['contribution_id'])
      ->addWhere('trxn_id', '=', $params['trxn_id'])
      ->execute()
      ->count();
    if ($alreadyRecorded === 0) {
      civicrm_api3('Mjwpayment', 'create_payment', $mjwPaymentParams);
    }
    $lock->release();
  }

  /**
   * Webhook event: charge.refunded
   * Process the charge.refunded event from Stripe
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function doChargeRefunded(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'charge') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    // Cancelling an uncaptured paymentIntent triggers charge.refunded but we don't want to process that
    if (empty($this->api->getValueFromStripeObject('captured', 'Boolean', $this->getData()->object))) {
      $return->ok = TRUE;
      return $return;
    }

    // Charge ID is required
    $chargeID = $this->api->getValueFromStripeObject('charge_id', 'String', $this->getData()->object);
    if (!$chargeID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing charge_id');
      return $return;
    }

    // Invoice ID is optional
    $invoiceID = $this->api->getValueFromStripeObject('invoice_id', 'String', $this->getData()->object);

    // This gives us the refund date + reason code
    $refunds = $this->getPaymentProcessor()->stripeClient->refunds->all(['charge' => $chargeID, 'limit' => 1]);
    $refund = $refunds->data[0];

    // Stripe does not refund fees - see https://support.stripe.com/questions/understanding-fees-for-refunded-payments
    // This gives us the actual amount refunded
    $amountRefunded = $this->api->getValueFromStripeObject('amount', 'Float', $refund);

    // Get the CiviCRM contribution that matches the Stripe metadata we have from the event
    $contribution = $this->findContribution($chargeID, $invoiceID);
    if (empty($contribution)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Contribution not found');
      return $return;
    }

    if (isset($contribution['payments'])) {
      foreach ($contribution['payments'] as $payment) {
        if ($payment['trxn_id'] === $refund->id) {
          $return->message = $this->formatResultMessage(__FUNCTION__, 'Refund already recorded in CiviCRM', ['refund ID' => $refund->id]);
          $return->ok = TRUE;
          return $return;
        }
        if ($payment['trxn_id'] === $chargeID) {
          // This triggers the financial transactions/items to be updated correctly.
          $cancelledPaymentID = $payment['id'];
        }
      }
    }

    $refundParams = [
      'contribution_id' => $contribution['id'],
      'total_amount' => 0 - abs($amountRefunded),
      'trxn_date' => date('YmdHis', $refund->created),
      'trxn_result_code' => $refund->reason,
      'fee_amount' => 0,
      'trxn_id' => $refund->id,
      'order_reference' => $invoiceID,
    ];

    if (!empty($cancelledPaymentID)) {
      $refundParams['cancelled_payment_id'] = $cancelledPaymentID;
    }

    $lock = $this->acquireContributionLock(__FUNCTION__, (string) $refundParams['contribution_id'], $return);
    if ($lock === NULL) {
      return $return;
    }
    $refundPayment = civicrm_api3('Payment', 'get', [
      'trxn_id' => $refundParams['trxn_id'],
      'total_amount' => $refundParams['total_amount'],
    ]);
    if (!empty($refundPayment['count'])) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Refund already recorded', ['coid' => $contribution['id']]);
      $return->ok = TRUE;
    }
    else {
      $this->updateContributionRefund($refundParams);
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Refund recorded', ['coid' => $contribution['id']]);
      $return->ok = TRUE;
    }
    $lock->release();
    return $return;
  }

  /**
   * Webhook event: charge.failed
   * One-time donation and per invoice payment
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doChargeFailed(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'charge') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    // If we don't have a customer_id we can't do anything with it!
    // It's quite likely to be a fraudulent/spam so we ignore.
    if (empty($this->api->getValueFromStripeObject('customer_id', 'String', $this->getData()->object))) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'ignoring - no customer_id');
      $return->ok = TRUE;
      return $return;
    }

    // Charge ID is required
    $chargeID = $this->api->getValueFromStripeObject('charge_id', 'String', $this->getData()->object);
    if (!$chargeID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing charge_id');
      return $return;
    }

    $paymentIntentID = $this->api->getValueFromStripeObject('payment_intent_id', 'String', $this->getData()->object);
    // Invoice ID is optional
    $invoiceID = $this->api->getValueFromStripeObject('invoice_id', 'String', $this->getData()->object);

    $contribution = $this->findContribution($chargeID, $invoiceID, '', $paymentIntentID);
    if (empty($contribution)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Contribution not found');
      return $return;
    }

    $failedContributionParams = [
      'contribution_id' => $contribution['id'],
      // 'failure_date' => $this->api->getValueFromStripeObject('receive_date', 'String', $this->getData()->object),
      'failure_reason' => $this->api->getValueFromStripeObject('failure_message', 'String', $this->getData()->object),
      'data' => $this->getData(),
      'identifier' => $chargeID,
      'level' => LogLevel::ERROR,
    ];
    // Fallback from invoiceID to chargeID. We can't use ?? because invoiceID might be empty string ie. '' and not NULL
    $failedContributionParams['order_reference'] = empty($invoiceID) ? $chargeID : $invoiceID;
    $this->updateContributionFailed($failedContributionParams);

    $return->message = $this->formatResultMessage(__FUNCTION__, 'OK', ['coid' => $contribution['id']]);
    $return->ok = TRUE;
    return $return;

  }

  /**
   * Webhook event: checkout.session.completed
   *
   * @return \stdClass
   */
  public function doCheckoutSessionCompleted(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'checkout.session') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    // Invoice ID is required
    $clientReferenceID = $this->api->getValueFromStripeObject('client_reference_id', 'String', $this->getData()->object);
    if (!$clientReferenceID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing client_reference_id');
      return $return;
    }

    $contribution = Contribution::get(FALSE)
      ->addWhere('invoice_id', '=', $clientReferenceID)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute()
      ->first();
    if (empty($contribution)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'contribution not found for client_reference_id');
      return $return;
    }

    /*
     * Serialise on the contribution before reading anything off it.
     *
     * This handler writes trxn_id unconditionally, and it is not the only
     * writer: doChargeSucceeded records the same contribution from a
     * different webhook, and Stripe delivers the two with no ordering
     * guarantee. recordAdditionalPayment() already takes this exact lock,
     * so acquiring it here makes the two contend rather than interleave.
     *
     * The lookup above establishes identity only. Anything read before the
     * lock may already be stale, so re-read under it.
     */
    $lock = $this->acquireContributionLock(__FUNCTION__, (string) $contribution['id'], $return);
    if ($lock === NULL) {
      return $return;
    }
    $contribution = Contribution::get(FALSE)
      ->addWhere('id', '=', $contribution['id'])
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute()
      ->first();

    // For one-off we have a paymentintentID
    $paymentIntentID = $this->api->getValueFromStripeObject('payment_intent_id', 'String', $this->getData()->object);

    // For subscription we have invoice + subscription
    $invoiceID = $this->api->getValueFromStripeObject('invoice_id', 'String', $this->getData()->object);
    $subscriptionID = $this->api->getValueFromStripeObject('subscription_id', 'String', $this->getData()->object);

    if (!empty($invoiceID)) {
      $contributionTrxnID = $invoiceID;
    }
    elseif (!empty($paymentIntentID)) {
      $contributionTrxnID = $paymentIntentID;
    }
    else {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing invoiceID or paymentIntentID');
      $lock->release();
      return $return;
    }

    // Update the paymentintent tracking record (created when the Checkout
    // Session was started) as a safety net in case the browser never
    // returned to continueCheckout() (eg. closed tab, network drop).
    $checkoutSessionID = $this->api->getValueFromStripeObject('checkout_session_id', 'String', $this->getData()->object);
    \CRM_Stripe_BAO_StripePaymentintent::writeRecord([
      'identifier' => $checkoutSessionID,
      'stripe_intent_id' => $paymentIntentID ?: NULL,
      'contribution_id' => $contribution['id'],
      'payment_processor_id' => $this->_paymentProcessor->getID(),
      'status' => 'complete',
    ]);

    $paymentMethodOptionValueID = $this->getPaymentMethodForContribution($paymentIntentID);

    $contributionUpdate = Contribution::update(FALSE)
      ->addWhere('id', '=', $contribution['id'])
      ->addValue('trxn_id', $contributionTrxnID);
    if (!empty($paymentMethodOptionValueID)) {
      $contributionUpdate->addValue('payment_instrument_id', $paymentMethodOptionValueID);
    }
    $contributionUpdate->execute();

    if (!empty($subscriptionID) && !empty($contribution['contribution_recur_id'])) {
      ContributionRecur::update(FALSE)
        ->addWhere('id', '=', $contribution['contribution_recur_id'])
        ->addValue('processor_id', $subscriptionID)
        ->execute();
    }

    // The charge.succeeded and invoice.paid (for recurring contributions)
    // notices often arrive before checkout.session.completed and we have no
    // way to match it to a contribution so it will be ignored. Now we have
    // processed checkout.session.completed see if we need to process
    // charge.succeeded or invoice.paid again.
    if (!empty($subscriptionID) && !empty($contribution['contribution_recur_id'])) {
      $identifier = $subscriptionID;
      $trigger = 'invoice.paid';
    }
    else {
      $identifier = $paymentIntentID;
      $trigger = 'charge.succeeded';
    }
    $webhook = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
      ->addSelect('id', 'event_id')
      ->addWhere('identifier', 'CONTAINS', $identifier)
      ->addWhere('trigger', '=', $trigger)
      ->addWhere('status', '=', 'success')
      ->addOrderBy('created_date', 'DESC')
      ->execute()
      ->first();
    if (!empty($webhook)) {
      // Stripe redelivers events, so the queue can hold several rows for one
      // event_id. The dedup guard only processes the lowest id and rejects the
      // rest, so re-queue that row - re-queueing any other is a no-op.
      $requeueID = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
        ->addSelect('id')
        ->addWhere('event_id', '=', $webhook['event_id'])
        ->addOrderBy('id', 'ASC')
        ->execute()
        ->first()['id'] ?? $webhook['id'];

      // Flag for re-processing
      \Civi\Api4\PaymentprocessorWebhook::update(FALSE)
        ->addValue('status', 'new')
        ->addValue('processed_date', NULL)
        ->addWhere('id', '=', $requeueID)
        ->execute();
      $return->message = $this->formatResultMessage(__FUNCTION__, "{$trigger} flagged for re-process", ['coid' => $contribution['id']]);
    }
    else {
      $return->message = $this->formatResultMessage(__FUNCTION__, "No suitable {$trigger} found to re-process for {$identifier}", ['coid' => $contribution['id']]);
    }

    $lock->release();
    $return->ok = TRUE;
    return $return;
  }

  /**
   * Webhook event: invoice.paid / invoice.payment_succeeded
   * Invoice changed to paid. This is nearly identical to invoice.payment_succeeded
   *
   * The invoice.payment_successful type Event object is created and sent to any webhook endpoints configured
   *   to accept that type of Event when the PaymentIntent powering the payment of an Invoice is used successfully.
   * The invoice.paid type Event object is created and sent to any webhook endpoints configured to accept that
   *   type of Event when the Invoice object has its paid property modified to a "true" value
   *   (see https://stripe.com/docs/api/invoices/object#invoice_object-paid).
   *
   * Successful recurring payment. Either we are completing an existing contribution or it's the next one in a subscription
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doInvoicePaid(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'invoice') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    $stripeInvoice = $this->getData()->object;
    // Invoice ID is required
    $invoiceID = $this->api->getValueFromStripeObject('invoice_id', 'String', $stripeInvoice);
    if (!$invoiceID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing Stripe invoice_id');
      return $return;
    }

    $chargeID = $this->api->getValueFromStripeObject('charge_id', 'String', $stripeInvoice);
    $subscriptionID = $this->api->getValueFromStripeObject('subscription_id', 'String', $stripeInvoice);
    if (empty($subscriptionID)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing Stripe subscription_id');
      return $return;
    }

    $contributionRecur = $this->getRecurFromStripeObject($subscriptionID, $stripeInvoice);
    if (empty($contributionRecur)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('No contributionRecur record found in CiviCRM. Ignored'));
      $return->ok = TRUE;
      return $return;
    }

    // Acquire the lock to find/create contribution
    $lock = $this->acquireContributionLock(__FUNCTION__, $invoiceID, $return);
    if ($lock === NULL) {
      return $return;
    }

    // We *normally/ideally* expect to be able to find the contribution,
    // since the logical order of events would be invoice.finalized first which
    // creates a contribution; then invoice.payment_succeeded/paid following, which would
    // find it.
    $contribution = $this->findContribution($chargeID, $invoiceID, $subscriptionID);
    if (empty($contribution)) {
      // We were unable to locate the Contribution; it could be the next one in a subscription.
      if (empty($contributionRecur['id'])) {
        // Hmmm. We could not find the contribution recur record either. Silently ignore this event(!)
        $return->ok = TRUE;
        $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('No contribution or recur record found in CiviCRM. Ignored'));
        $lock->release();
        return $return;
      }

      // The first invoice pays for the Contribution created at form submit, not
      // a new instalment. findContribution() above cannot match it under
      // Checkout, so adopt it rather than creating a duplicate.
      if ($this->isFirstInvoiceForSubscription($stripeInvoice)) {
        $contribution = $this->findInitialContributionForRecur($contributionRecur);
      }

      if (empty($contribution)) {
        // We have a recurring contribution but have not yet received invoice.finalized so we don't have the next contribution yet.
        // invoice.payment_succeeded sometimes comes before invoice.finalized so trigger the same behaviour here to create a new contribution

        $contributionID = $this->createNextContributionForRecur($chargeID, $invoiceID, $contributionRecur);
        // Now get the contribution we just created.
        $contribution = Contribution::get(FALSE)
          ->addWhere('id', '=', $contributionID)
          ->execute()
          ->first();
      }
    }
    // Release the lock to find/create contribution
    $lock->release();

    // Now acquire lock to record payment on the contribution
    $lock = $this->acquireContributionLock(__FUNCTION__, (string) $contribution['id'], $return);
    if ($lock === NULL) {
      return $return;
    }

    // By this point we should have a contribution
    if (civicrm_api3('Mjwpayment', 'get_payment', [
      'trxn_id' => $chargeID,
      'status_id' => 'Completed',
    ])['count'] > 0) {
      // Payment already recorded
      $return->ok = TRUE;
      $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('Payment already recorded'), ['coid' => $contribution['id']]);
      $lock->release();
      return $return;
    }

    $pendingContributionStatusID = (int) \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $failedContributionStatusID = (int) \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed');
    $statusesAllowedToComplete = [$pendingContributionStatusID, $failedContributionStatusID];

    // If contribution is in Pending or Failed state record payment and transition to Completed
    if (in_array($contribution['contribution_status_id'], $statusesAllowedToComplete)) {
      $balanceTransactionDetails = $this->api->getDetailsFromBalanceTransactionByChargeID($chargeID);
      $contributionParams = [
        'contribution_id' => $contribution['id'],
        'trxn_date' => $this->api->getValueFromStripeObject('receive_date', 'String', $stripeInvoice),
        'order_reference' => $invoiceID,
        'trxn_id' => $chargeID,
        'total_amount' => $this->api->getValueFromStripeObject('amount', 'String', $stripeInvoice),
        // 'fee_amount' Added below via $balanceTransactionDetails
        'contribution_status_id' => $contribution['contribution_status_id'],
        // We pass this through in case we changed it to a Stripe specific method (eg. in doCheckoutSessionCompleted)
        'payment_instrument_id' => $contribution['payment_instrument_id'],
      ];
      foreach ($balanceTransactionDetails as $key => $value) {
        $contributionParams[$key] = $value;
      }

      $this->updateContributionCompleted($contributionParams);
      // The contributionRecur as it's updated automatically by Contribution.completetransaction
      // However, it will only update status if in "Pending" or "In Progress"

      // Get the contributionRecur and if not in "In Progress" update it to that since we have a successful payment
      $recur = ContributionRecur::get(FALSE)
        ->addSelect('contribution_status_id:name')
        ->addWhere('id', '=', $contributionRecur['id'])
        ->execute()
        ->first();
      if (!empty($recur)) {
        $recurValues = [];
        if ($recur['contribution_status_id:name'] !== 'In Progress') {
          $recurValues['contribution_status_id:name'] = 'In Progress';
        }
        // next_sched_contribution_date is calculated automatically by CiviCRM in CRM_Contribute_BAO_ContributionRecur::updateOnNewPayment()
        //   when a new payment is added and time will be set to 00:00. But we can get the actual value directly from Stripe.
        $stripeSubscription = $this->getPaymentProcessor()->stripeClient->subscriptions->retrieve($subscriptionID);
        $nextSchedContributionDate = \CRM_Stripe_Api::getObjectParam('next_sched_contribution_date', $stripeSubscription);
        if (!empty($nextSchedContributionDate)) {
          $recurValues['next_sched_contribution_date'] = $nextSchedContributionDate;
        }
        if (!empty($recurValues)) {
          ContributionRecur::update(FALSE)
            ->setValues($recurValues)
            ->addWhere('id', '=', $contributionRecur['id'])
            ->execute();
        }
      }
    }
    $lock->release();

    $this->handleInstallmentsForSubscription($subscriptionID, $contributionRecur['id']);
    $return->message = $this->formatResultMessage(__FUNCTION__, 'OK', ['coid' => $contribution['id']]);
    $return->ok = TRUE;
    return $return;
  }

  /**
   * Webhook event: invoice.finalized
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function doInvoiceFinalized(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'invoice') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    // Invoice ID is required
    $invoiceID = $this->api->getValueFromStripeObject('invoice_id', 'String', $this->getData()->object);
    if (!$invoiceID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing invoice_id');
      return $return;
    }

    $chargeID = $this->api->getValueFromStripeObject('charge_id', 'String', $this->getData()->object);
    $subscriptionID = $this->api->getValueFromStripeObject('subscription_id', 'String', $this->getData()->object);
    $contributionRecur = $this->getRecurFromStripeObject($subscriptionID, $this->getData()->object);
    if (empty($contributionRecur)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('No contributionRecur record found in CiviCRM. Ignored'));
      $return->ok = TRUE;
      return $return;
    }

    $contribution = $this->findContribution($chargeID, $invoiceID, $subscriptionID);

    // Same first-invoice case as doInvoicePaid.
    if (empty($contribution) && $this->isFirstInvoiceForSubscription($this->getData()->object)) {
      $contribution = $this->findInitialContributionForRecur($contributionRecur);
    }

    // An invoice has been created and finalized (ready for payment)
    // This usually happens automatically through a Stripe subscription
    if (empty($contribution)) {
      // Unable to find a Contribution.
      $contributionID = $this->createNextContributionForRecur($chargeID, $invoiceID, $contributionRecur);
      $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('Created Contribution'), ['coid' => $contributionID]);
      $return->ok = TRUE;
      return $return;
    }

    // For a future recur start date we setup the initial contribution with the
    // Stripe subscriptionID because we didn't have an invoice.
    // Now we do we can map subscription_id to invoice_id so payment can be recorded
    // via subsequent IPN requests (eg. invoice.payment_succeeded)
    if ($contribution['trxn_id'] === $subscriptionID) {
      Contribution::update(FALSE)
        ->addWhere('id', '=', $contribution['id'])
        ->addValue('trxn_id', $invoiceID)
        ->execute();
      $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('Updated contribution trxn_id to invoiceID (was subscriptionID)'), ['coid' => $contribution['id']]);
    }
    if (empty($return->message)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('Nothing to do'), ['coid' => $contribution['id']]);
    }
    $return->ok = TRUE;
    return $return;
  }

  /**
   * Webhook event: invoice.payment_failed
   * Failed recurring payment. Either we are failing an existing contribution or it's the next one in a subscription
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function doInvoicePaymentFailed(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'invoice') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    // Invoice ID is required
    $invoiceID = $this->api->getValueFromStripeObject('invoice_id', 'String', $this->getData()->object);
    if (!$invoiceID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing invoice_id');
      return $return;
    }

    $chargeID = $this->api->getValueFromStripeObject('charge_id', 'String', $this->getData()->object);

    // Get the CiviCRM contribution that matches the Stripe metadata we have from the event
    $contribution = $this->findContribution($chargeID, $invoiceID);
    if (empty($contribution)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Contribution not found');
      return $return;
    }

    $pendingContributionStatusID = (int) \CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');

    if ($contribution['contribution_status_id'] == $pendingContributionStatusID) {
      // If this contribution is Pending, set it to Failed.

      // To obtain the failure_message we need to look up the charge object
      $failureMessage = '';

      if ($chargeID) {
        $stripeCharge = $this->getPaymentProcessor()->stripeClient->charges->retrieve($chargeID);
        $failureMessage = $this->api->getValueFromStripeObject('failure_message', 'String', $stripeCharge);
        $failureMessage = is_string($failureMessage) ? $failureMessage : '';
      }

      $failedContributionParams = [
        'contribution_id' => $contribution['id'],
        'order_reference' => $invoiceID,
        'failure_reason' => $failureMessage,
        'data' => $this->getData(),
        'identifier' => $invoiceID,
        'level' => LogLevel::ERROR,
      ];

      $this->updateContributionFailed($failedContributionParams);
    }
    $return->message = $this->formatResultMessage(__FUNCTION__, 'OK', ['coid' => $contribution['id']]);
    $return->ok = TRUE;
    return $return;
  }

  /**
   * Subscription is cancelled.
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doCustomerSubscriptionDeleted(): \stdClass {
    $return = $this->getResultObject();

    // Check we have the right data object for this event
    if (($this->getData()->object->object ?? '') !== 'subscription') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    $subscriptionID = $this->api->getValueFromStripeObject('subscription_id', 'String', $this->getData()->object);
    if (!$subscriptionID) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing subscription_id');
      return $return;
    }

    $contributionRecur = $this->getRecurFromStripeObject($subscriptionID, $this->getData()->object);
    if (empty($contributionRecur)) {
      // Subscription was not found in CiviCRM
      $result = [];
      \CRM_Mjwshared_Hook::webhookEventNotMatched('stripe', $this, 'subscription_not_found', $result);
      if (empty($result['contributionRecur'])) {
        $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('No contributionRecur record found in CiviCRM. Ignored'));
        $return->ok = TRUE;
        return $return;
      }
      else {
        $contributionRecur = $result['contributionRecur'];
      }
    }

    // Cancel the recurring contribution
    $this->updateRecurCancelled(['id' => $contributionRecur['id'], 'cancel_date' => $this->api->getValueFromStripeObject('cancel_date', 'String', $this->getData()->object)]);

    $return->message = $this->formatResultMessage(__FUNCTION__, 'cancelled', ['crid' => $contributionRecur['id']]);
    $return->ok = TRUE;
    return $return;
  }

  /**
   * Subscription is updated. We don't currently do anything with this
   *
   * @return \stdClass
   */
  public function doCustomerSubscriptionUpdated(): \stdClass {
    $return = $this->getResultObject();

    /** @var \Stripe\StripeObject $data */
    $stripeData = $this->getData();
    if (!($stripeData->object instanceof \Stripe\Subscription)) {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid data');
      return $return;
    }

    // Check we have the right data object for this event
    if (($stripeData->object->object ?? '') !== 'subscription') {
      $return->message = $this->formatResultMessage(__FUNCTION__, 'Invalid object type');
      return $return;
    }

    $subscriptionID = $this->api->getValueFromStripeObject('subscription_id', 'String', $stripeData->object);

    $contributionRecur = $this->getRecurFromStripeObject($subscriptionID, $stripeData->object);
    if (empty($contributionRecur)) {
      // Subscription was not found in CiviCRM
      $result = [];
      \CRM_Mjwshared_Hook::webhookEventNotMatched('stripe', $this, 'subscription_not_found', $result);
      if (empty($result['contributionRecur'])) {
        $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('No contributionRecur record found in CiviCRM. Ignored'));
        $return->ok = TRUE;
        return $return;
      }
      $contributionRecur = $result['contributionRecur'];
    }

    if (!isset($stripeData->previous_attributes)) {
      // Nothing changed?!
      $return->message = $this->formatResultMessage(__FUNCTION__, E::ts('No changes. Ignored'));
      $return->ok = TRUE;
      return $return;
    }

    // First work out what changed. This is held in "previous_attributes" on webhook data
    $previousAttributes = $stripeData->previous_attributes;
    // Simple check that we actually have some items data
    // Otherwise it could just be a metadata change which we are not interested in.
    $amountHasChanged = FALSE;
    if (!empty($previousAttributes->items->data)) {
      $amountHasChanged = TRUE;
    }

    if ($amountHasChanged) {
      $subscriptionItems = $stripeData->object->items->data;
      $calculatedItems = $this->api->calculateItemsForSubscription($subscriptionID, $subscriptionItems);
    }

    // $calculatedItems now contains array of new prices by key [currency]_[frequency_unit]_[frequency_interval]
    // Eg. $calculatedItems[usd_month_1] = [
    //       'currency' => 'usd',
    //       'amount' => '2000', (amount is in pence)
    //     ];

    // Now check if recurring contribution matches frequency
    $contributionRecurKey = mb_strtolower($contributionRecur['currency']) . "_{$contributionRecur['frequency_unit']}_{$contributionRecur['frequency_interval']}";
    if (isset($calculatedItems[$contributionRecurKey])) {
      $calculatedItem = $calculatedItems[$contributionRecurKey];
      $templateContribution = \CRM_Contribute_BAO_ContributionRecur::getTemplateContribution($contributionRecur['id']);
      if (empty($templateContribution)) {
        $return->message = $this->formatResultMessage(__FUNCTION__, 'Missing template contribution for recur: ' . $contributionRecur['id']);
        return $return;
      }
      if (!Money::of((string) $calculatedItem['amount'], mb_strtoupper($calculatedItem['currency']))
        ->isAmountAndCurrencyEqualTo(Money::of((string) $templateContribution['total_amount'], $templateContribution['currency']))) {
        // Create a new template contribution to update the amount
        ContributionRecur::updateAmountOnRecurMJW(FALSE)
          ->setAmount($calculatedItem['amount'])
          ->addWhere('id', '=', $contributionRecur['id'])
          ->execute();
        $return->message = $this->formatResultMessage(__FUNCTION__, 'recur: ' . $contributionRecur['id'] . '; new amount: ' . $calculatedItem['amount'] . ' currency: ' . $calculatedItem['currency']);
      }
      else {
        $return->message = $this->formatResultMessage(__FUNCTION__, 'recur already updated: ' . $contributionRecur['id'] . '; amount: ' . $calculatedItem['amount'] . ' currency: ' . $calculatedItem['currency']);
      }
    }

    $return->ok = TRUE;
    return $return;
  }

  /**
   * This retrieves the matching paymentInstrument/paymentMethod OptionValue and creates it if missing
   * So that the Contribution is recorded with the actual payment method instead of the default (eg. "Credit Card").
   *
   * @param string $paymentIntentID
   *
   * @return int|null
   */
  private function getPaymentMethodForContribution(string $paymentIntentID): ?int {
    if (empty($paymentIntentID)) {
      // Checkout Sessions in subscription mode carry no payment_intent - Stripe
      // puts it on the generated invoice instead - so doCheckoutSessionCompleted
      // reaches here with an empty string on every subscription signup.
      // paymentIntents->retrieve('') then throws "The resource ID cannot be null
      // or whitespace" from AbstractService::buildPath() - client-side, before
      // any request is made - and the catch below logs it as an error. So the
      // cost is an error line per signup rather than a wasted API call, but
      // there is still nothing to look up here.
      //
      // Returning NULL is not silent in its effect: the Contribution keeps the
      // default payment instrument rather than the actual method. Note that,
      // so anyone asking later why subscription signups are all recorded as the
      // default has something to find.
      if ((bool) \Civi::settings()->get('stripe_ipndebug')) {
        $message = $this->getPaymentProcessor()->getPaymentProcessorLabel() . ': ' . $this->getEventID()
          . ': No payment_intent on this event, so the payment method cannot be looked up.'
          . ' Contribution keeps the default payment instrument. This is expected for'
          . ' Checkout Sessions in subscription mode.';
        \Civi::log('stripe')->debug($message);
      }
      return NULL;
    }

    try {
      $paymentIntent = $this->getPaymentProcessor()->stripeClient->paymentIntents->retrieve($paymentIntentID);
      $paymentMethodOptionValueID = NULL;
      /**
       * @var \Stripe\PaymentIntent $paymentIntent
       */
      if (!empty($paymentIntent->payment_method_types)) {
        $paymentMethod = reset($paymentIntent->payment_method_types);
        $supportedPaymentMethods = \CRM_Stripe_Api::getListOfSupportedPaymentMethodsCheckout();
        if (array_key_exists($paymentMethod, $supportedPaymentMethods)) {
          // Update the payment method on the contribution
          $paymentMethodOptionValueID = \Civi\Api4\OptionValue::get(FALSE)
            ->addWhere('option_group_id.name', '=', 'payment_instrument')
            ->addWhere('name', '=', 'stripe_' . $paymentMethod)
            ->execute()
            ->first()['value'] ?? NULL;
          if (empty($paymentMethodOptionValueID)) {
            // Create the new paymentInstrument optionValue and associated link to FinancialAccount
            $paymentMethodOptionValue = \Civi\Api4\OptionValue::create(FALSE)
              ->addValue('option_group_id.name', 'payment_instrument')
              ->addValue('label', $supportedPaymentMethods[$paymentMethod])
              ->addValue('name', 'stripe_' . $paymentMethod)
              ->execute()
              ->first();
            \Civi\Api4\EntityFinancialAccount::create(FALSE)
              ->addValue('entity_table', 'civicrm_option_value')
              ->addValue('entity_id', $paymentMethodOptionValue['id'])
              ->addValue('financial_account_id:name', 'Payment Processor Account')
              ->addValue('account_relationship:name', 'Asset Account is')
              ->execute();
            $paymentMethodOptionValueID = $paymentMethodOptionValue['value'];
          }
        }
      }
      return (int) $paymentMethodOptionValueID ?? NULL;
    }
    catch (\Throwable $e) {
      \Civi::log()->error('Stripe: Error in getPaymentMethodForContribution: ' . $e->getMessage());
    }
    return NULL;
  }

}
