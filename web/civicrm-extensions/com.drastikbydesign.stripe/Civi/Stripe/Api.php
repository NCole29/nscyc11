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

namespace Civi\Stripe;

use Civi\Payment\Exception\PaymentProcessorException;
use CRM_Stripe_ExtensionUtil as E;

class Api {

  use \CRM_Core_Payment_MJWIPNTrait;

  public function __construct($paymentProcessor) {
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   * @param string $name The key of the required value
   * @param string $dataType The datatype of the required value (eg. String)
   * @param \Stripe\StripeObject $stripeObject
   * @param bool $allowOverride
   *
   * @return int|mixed|null
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function getValueFromStripeObject(string $name, string $dataType, $stripeObject, $allowOverride = TRUE) {
    if (\Civi::settings()->get('stripe_record_payoutcurrency') && $allowOverride) {
      // Intercept amount/currency as we need to use the values from the balancetransaction
      if (in_array($name, ['amount', 'currency'])) {
        try {
          $balanceTransactionDetails = $this->getDetailsFromBalanceTransactionByChargeObject($stripeObject);
          switch ($name) {
            case 'amount':
              if (isset($balanceTransactionDetails['payout_amount'])) {
                return $balanceTransactionDetails['payout_amount'];
              }
              break;

            case 'currency':
              if (isset($balanceTransactionDetails['payout_currency'])) {
                return $balanceTransactionDetails['payout_currency'];
              }
              break;
          }
        }
        catch (PaymentProcessorException $e) {
          \Civi::log('stripe')->warning($this->getPaymentProcessor()->getLogPrefix() . "getValueFromStripeObject($name, $dataType, $stripeObject->object) getDetailsFromBalanceTransaction failed: " . $e->getMessage());
          // We allow this to continue with "normal" processing as this feature is experimental and we don't want to break normal workflow
          // It means we'll end up with values for amount/currency in the amount charged per normal behaviour.
        }
      }
    }

    $value = \CRM_Stripe_Api::getObjectParam($name, $stripeObject);
    $value = \CRM_Utils_Type::validate($value, $dataType, FALSE);
    return $value;
  }

  /**
   * @param string $chargeID
   *
   * @return float[]
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function getDetailsFromBalanceTransactionByChargeID(string $chargeID): array {
    if (empty($chargeID)) {
      return [];
    }
    $chargeObject = $this->getPaymentProcessor()->stripeClient->charges->retrieve($chargeID);
    if ($this->getValueFromStripeObject('status', 'String', $chargeObject) !== 'succeeded') {
      // Only successful charges have a balanceTransaction
      return [];
    }
    $balanceTransactionID = $this->getValueFromStripeObject('balance_transaction', 'String', $chargeObject);
    return $this->getDetailsFromBalanceTransaction($balanceTransactionID, $chargeObject);
  }

  /**
   * @param \Stripe\StripeObject $chargeObject
   *
   * @return float[]
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function getDetailsFromBalanceTransactionByChargeObject($chargeObject): array {
    if ($chargeObject && ($chargeObject->object === 'charge')) {
      $balanceTransactionID = $this->getValueFromStripeObject('balance_transaction', 'String', $chargeObject);
      if (empty($balanceTransactionID)) {
        // This can happen if PaymentIntent was setup with capture_method=automatic_async
        $chargeObject = $this->getPaymentProcessor()->stripeClient->charges->retrieve($chargeObject->id);
        $balanceTransactionID = $this->getValueFromStripeObject('balance_transaction', 'String', $chargeObject);
        if (empty($balanceTransactionID)) {
          throw new PaymentProcessorException('BalanceTransactionID not found in Stripe Charge object.');
        }
      }
      return $this->getDetailsFromBalanceTransaction($balanceTransactionID, $chargeObject);
    }
    else {
      // We don't have any way of getting the balance_transaction ID.
      throw new \Civi\Payment\Exception\PaymentProcessorException('Cannot call getDetailsFromBalanceTransaction when stripeObject is not of type "charge"');
    }
  }

  /**
   * @param string $balanceTransactionID
   * @param \Stripe\StripeObject $chargeObject
   *
   * @return float[]
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function getDetailsFromBalanceTransaction(string $balanceTransactionID, $chargeObject): array {
    if (empty($balanceTransactionID)) {
      // This shouldn't be able to happen, but catch it in case it does so we can debug
      throw new \Civi\Payment\Exception\PaymentProcessorException('getDetailsFromBalanceTransaction: empty balanceTransactionID!');
    }

    // We may need to get balance transaction details multiple times when processing.
    // The first time we retrieve from stripe but then we use the cached version.
    if (isset(\Civi::$statics[__CLASS__][__FUNCTION__]['balanceTransactions'][$balanceTransactionID])) {
      return \Civi::$statics[__CLASS__][__FUNCTION__]['balanceTransactions'][$balanceTransactionID];
    }

    try {
      $balanceTransaction = $this->getPaymentProcessor()->stripeClient->balanceTransactions->retrieve($balanceTransactionID);
    }
    catch (\Exception $e) {
      throw new \Civi\Payment\Exception\PaymentProcessorException("Error retrieving balanceTransaction {$balanceTransactionID}. " . $e->getMessage());
    }

    $chargeCurrency = $this->getValueFromStripeObject('currency', 'String', $chargeObject, FALSE);
    $chargeFee = $this->getPaymentProcessor()->getFeeFromBalanceTransaction($balanceTransaction, $chargeCurrency);
    \Civi::$statics[__CLASS__][__FUNCTION__]['balanceTransactions'][$balanceTransactionID] = [
      'fee_amount' => \Civi::settings()->get('stripe_record_payoutcurrency') ? $balanceTransaction->fee / 100 : $chargeFee,
      'available_on' => \CRM_Stripe_Api::formatDate($balanceTransaction->available_on),
      'exchange_rate' => $balanceTransaction->exchange_rate,
      'charge_amount' => $this->getValueFromStripeObject('amount', 'Float', $chargeObject, FALSE),
      'charge_currency' => $chargeCurrency,
      'charge_fee' => $chargeFee,
      'payout_amount' => $balanceTransaction->amount / 100,
      'payout_currency' => \CRM_Stripe_Api::formatCurrency($balanceTransaction->currency),
      'payout_fee' => $balanceTransaction->fee / 100,
    ];
    return \Civi::$statics[__CLASS__][__FUNCTION__]['balanceTransactions'][$balanceTransactionID];
  }

  /**
   * @param string $subscriptionID
   * @param array $itemsData
   *   Array of \Stripe\SubscriptionItem
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function calculateItemsForSubscription(string $subscriptionID, array $itemsData) {
    $calculatedItems = [];
    // Recalculate amount and update
    foreach ($itemsData as $item) {
      $subscriptionItem['subscriptionItemID'] = $this->getValueFromStripeObject('id', 'String', $item);
      $subscriptionItem['quantity'] = $this->getValueFromStripeObject('quantity', 'Int', $item);
      $subscriptionItem['unit_amount'] = $this->getValueFromStripeObject('unit_amount', 'Float', $item->price);

      $calculatedItem['currency'] = $this->getValueFromStripeObject('currency', 'String', $item->price);
      $calculatedItem['amount'] = $subscriptionItem['unit_amount'] * $subscriptionItem['quantity'];
      if ($this->getValueFromStripeObject('type', 'String', $item->price) === 'recurring') {
        $calculatedItem['frequency_unit'] = $this->getValueFromStripeObject('recurring_interval', 'String', $item->price);
        $calculatedItem['frequency_interval'] = $this->getValueFromStripeObject('recurring_interval_count', 'Int', $item->price);
      }

      if (empty($calculatedItem['frequency_unit'])) {
        \Civi::log('stripe')->warning("StripeIPN: {$subscriptionID} customer.subscription.updated:
            Non recurring subscription items are not supported");
      }
      else {
        $intervalKey = $calculatedItem['currency'] . '_' . $calculatedItem['frequency_unit'] . '_' . $calculatedItem['frequency_interval'];
        if (isset($calculatedItems[$intervalKey])) {
          // If we have more than one subscription item with the same currency and frequency add up the amounts and combine.
          $calculatedItem['amount'] += ($calculatedItems[$intervalKey]['amount'] ?? 0);
          $calculatedItem['subscriptionItem'] = $calculatedItems[$intervalKey]['subscriptionItem'];
        }
        $calculatedItem['subscriptionItem'][] = $subscriptionItem;
        $calculatedItems[$intervalKey] = $calculatedItem;
      }
    }
    return $calculatedItems;
  }

  /**
   * @param string $currency
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function getPaymentMethodsForCurrency(string $currency): array {
    $paymentMethods = \Civi::settings()->get('stripe_checkout_supported_payment_methods');
    $result = [];
    $supportedPaymentMethods = \CRM_Stripe_Api::getSupportedPaymentMethodsCheckout();
    foreach ($supportedPaymentMethods as $supportedPaymentMethod) {
      if (in_array($supportedPaymentMethod['name'], $paymentMethods)) {
        // Check for all currencies
        if (in_array('*', $supportedPaymentMethod['currencies'])) {
          $result[] = $supportedPaymentMethod['name'];
        }
        else {
          foreach ($supportedPaymentMethod['currencies'] as $methodCurrency) {
            if ($currency === $methodCurrency) {
              $result[] = $supportedPaymentMethod['name'];
            }
            break;
          }
        }
      }
    }
    if (empty($result)) {
      throw new PaymentProcessorException('There are no valid Stripe payment methods enabled for this configuration. Check currency etc.');
    }
    return $result;
  }

}
