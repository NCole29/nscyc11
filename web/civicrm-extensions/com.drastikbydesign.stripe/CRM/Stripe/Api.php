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

use CRM_Stripe_ExtensionUtil as E;

class CRM_Stripe_Api {

  /**
   * Metadata key carrying the CiviCRM ContributionRecur ID on the Stripe
   * subscription. Written at Checkout Session creation, read back below.
   */
  const METADATA_CONTRIBUTION_RECUR_ID = 'civicrm_contribution_recur_id';

  /**
   * @param string $name
   * @param \Stripe\StripeObject $stripeObject
   *
   * @return bool|float|int|string|null
   * @throws \Stripe\Exception\ApiErrorException
   */
  public static function getObjectParam($name, $stripeObject) {
    // object is a string containing the Stripe object name
    switch ($stripeObject->object) {
      case 'charge':
        /** @var \Stripe\Charge $stripeObject */
        switch ($name) {
          case 'charge_id':
            return (string) $stripeObject->id;

          case 'failure_code':
            return (string) $stripeObject->failure_code;

          case 'failure_message':
            return (string) $stripeObject->failure_message;

          case 'amount':
            return (float) $stripeObject->amount / 100;

          case 'refunded':
            return (bool) $stripeObject->refunded;

          case 'amount_refunded':
            return (float) $stripeObject->amount_refunded / 100;

          case 'customer_id':
            return (string) $stripeObject->customer;

          case 'balance_transaction':
            return (string) $stripeObject->balance_transaction;

          case 'receive_date':
          case 'created_date':
            return self::formatDate($stripeObject->created);

          case 'invoice_id':
            if (!isset($stripeObject->invoice)) {
              return '';
            }
            // Handle both "expanded" and "collapsed" response
            elseif (is_object($stripeObject->invoice)) {
              return (string) $stripeObject->invoice->id;
            }
            else {
              return (string) $stripeObject->invoice;
            }

          case 'captured':
            return (bool) $stripeObject->captured;

          case 'currency':
            return self::formatCurrency($stripeObject->currency);

          case 'payment_intent_id':
            return (string) $stripeObject->payment_intent;

          case 'description':
            return (string) $stripeObject->description;

          case 'status':
            // This might be "succeeded", "pending", "failed" (https://stripe.com/docs/api/charges/object#charge_object-status)
            return (string) $stripeObject->status;

        }
        break;

      case 'invoice':
        /** @var \Stripe\Invoice $stripeObject */
        switch ($name) {
          case 'charge_id':
            return (string) $stripeObject->charge;

          case 'invoice_id':
            return (string) $stripeObject->id;

          case 'receive_date':
            /*
             * The "created" date of the invoice does not equal the paid date but it *might* be the same.
             * We should use the paid_at below or lookup via the charge or paymentintent.
             * "status_transitions": {
             * "finalized_at": 1676295806,
             * "marked_uncollectible_at": null,
             * "paid_at": 1677591861,
             * "voided_at": null
             * },
             */
            if (!empty($stripeObject->status_transitions->paid_at)) {
              return self::formatDate($stripeObject->status_transitions->paid_at);
            }
            // Intentionally falls through to invoice_date

          case 'invoice_date':
            if (!empty($stripeObject->status_transitions->finalized_at)) {
              return self::formatDate($stripeObject->status_transitions->finalized_at);
            }
            // Intentionally falls through to created_date

          case 'created_date':
            return self::formatDate($stripeObject->created);

          case 'subscription_id':
            if (!empty($stripeObject->subscription)) {
              return (string) $stripeObject->subscription;
            }
            // Invoice.subscription is only set when the invoice was prepared for
            // exactly one subscription. If Stripe consolidated multiple subscriptions
            // (or non-subscription items) onto this invoice, fall back to the first
            // line item that has one - the field is still present there.
            foreach (($stripeObject->lines?->data ?? []) as $lineItem) {
              if (!empty($lineItem->subscription)) {
                return (string) $lineItem->subscription;
              }
            }
            return '';

          case 'subscription_metadata_recur_id':
            // Stripe copies the subscription's metadata onto every invoice it
            // generates, including the first. Lets an invoice be matched to its
            // recur without ContributionRecur.processor_id, which is not written
            // until checkout.session.completed is processed.
            if (empty($stripeObject->subscription_details->metadata[self::METADATA_CONTRIBUTION_RECUR_ID])) {
              return '';
            }
            return (string) $stripeObject->subscription_details->metadata[self::METADATA_CONTRIBUTION_RECUR_ID];

          case 'billing_reason':
            // Distinguishes the first invoice of a subscription
            // (subscription_create) from a renewal (subscription_cycle).
            return (string) ($stripeObject->billing_reason ?? '');

          case 'amount':
            return (float) $stripeObject->amount_due / 100;

          case 'amount_paid':
            return (float) $stripeObject->amount_paid / 100;

          case 'amount_remaining':
            return (float) $stripeObject->amount_remaining / 100;

          case 'currency':
            return self::formatCurrency($stripeObject->currency);

          case 'description':
            return (string) $stripeObject->description;

          case 'customer_id':
            return (string) $stripeObject->customer;

          case 'failure_message':
            // This is a coding error, but it looks like the general policy here is to return something. Could otherwise consider throwing an exception.
            Civi::log()->error("Coding error: CRM_Stripe_Api::getObjectParam failure_message is not a property on a Stripe Invoice object. Please alter your code to fetch the Charge and obtain the failure_message from that.");
            return '';

          case 'status':
            return self::mapInvoiceStatusToContributionStatus($stripeObject);

        }
        break;

      case 'subscription':
        /** @var \Stripe\Subscription $stripeObject */
        switch ($name) {
          case 'frequency_interval':
          case 'frequency_unit':
          case 'amount':
            $plan = [
              'amount' => 0,
              'interval' => '',
              'interval_count' => 0,
            ];
            foreach ($stripeObject->items as $item) {
              if ($item->price->active && ($item->quantity > 0)) {
                $plan['amount'] += $item->price->unit_amount * $item->quantity;
                $plan['interval'] = $item->price->recurring->interval;
                $plan['interval_count'] = $item->price->recurring->interval_count;
              }
            }

            switch ($name) {
              case 'frequency_interval':
                return (int) $plan['interval_count'];

              case 'frequency_unit':
                return (string) $plan['interval'];

              case 'amount':
                return (float) $plan['amount'] / 100;
            }
            break;

          case 'currency':
            return self::formatCurrency($stripeObject->currency);

          case 'plan_start':
            return self::formatDate($stripeObject->start_date);

          case 'cancel_date':
            return self::formatDate($stripeObject->canceled_at);

          case 'next_sched_contribution_date':
            return self::formatDate($stripeObject->current_period_end);

          case 'current_period_start':
            return self::formatDate($stripeObject->current_period_start);

          case 'cycle_day':
            return date("d", $stripeObject->billing_cycle_anchor);

          case 'subscription_id':
            return (string) $stripeObject->id;

          case 'subscription_metadata_recur_id':
            // As the 'invoice' case, but read from the subscription itself.
            if (empty($stripeObject->metadata[self::METADATA_CONTRIBUTION_RECUR_ID])) {
              return '';
            }
            return (string) $stripeObject->metadata[self::METADATA_CONTRIBUTION_RECUR_ID];

          case 'status_id':
            switch ($stripeObject->status) {
              case \Stripe\Subscription::STATUS_INCOMPLETE:
                return CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');

              case \Stripe\Subscription::STATUS_ACTIVE:
              case \Stripe\Subscription::STATUS_TRIALING:
                return CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');

              case \Stripe\Subscription::STATUS_PAST_DUE:
                return CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Overdue');

              case \Stripe\Subscription::STATUS_CANCELED:
              case \Stripe\Subscription::STATUS_UNPAID:
              case \Stripe\Subscription::STATUS_INCOMPLETE_EXPIRED:
              default:
                return CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Cancelled');
            }

          case 'status':
            return self::mapSubscriptionStatusToRecurStatus($stripeObject->status);

          case 'customer_id':
            return (string) $stripeObject->customer;
        }
        break;

      case 'checkout.session':
        /** @var \Stripe\Checkout\Session $stripeObject */
        switch ($name) {
          case 'checkout_session_id':
            return (string) $stripeObject->id;

          case 'client_reference_id':
            return (string) $stripeObject->client_reference_id;

          case 'customer_id':
            return (string) $stripeObject->customer;

          case 'invoice_id':
            return (string) $stripeObject->invoice;

          case 'payment_intent_id':
            return (string) $stripeObject->payment_intent;

          case 'subscription_id':
            return (string) $stripeObject->subscription;
        }
        break;

      case 'subscription_item':
        /** @var \Stripe\SubscriptionItem $stripeObject */
        // eg. unit_amount
        if (isset($stripeObject->$name)) {
          return $stripeObject->$name;
        }
        \Civi::log('stripe')->error('getObjectParam: Tried to get param "' . $name . '" from "' . $stripeObject->object . '" but it is not set');
        return NULL;

      case 'price':
        /** @var \Stripe\Price $stripeObject */
        switch ($name) {
          case 'unit_amount':
            return (float) $stripeObject->unit_amount / 100;

          case 'recurring_interval':
            // eg. "year"
            return (string) $stripeObject->recurring->interval ?? '';

          case 'recurring_interval_count':
            // eg 1
            return (int) $stripeObject->recurring->interval_count ?? 0;

          default:
            if (isset($stripeObject->$name)) {
              return $stripeObject->$name;
            }
            \Civi::log('stripe')->error('getObjectParam: Tried to get param "' . $name . '" from "' . $stripeObject->object . '" but it is not set');
            return NULL;
          // unit_amount
        }
        break;

      case 'refund':
        /** @var \Stripe\Refund $stripeObject */
        switch ($name) {
          case 'amount':
            return (float) $stripeObject->amount / 100;
        }
        break;

    }

    return NULL;
  }

  /**
   * Return a formatted date from a stripe timestamp or NULL if not set
   * @param int $stripeTimestamp
   *
   * @return string|null
   */
  public static function formatDate($stripeTimestamp) {
    return $stripeTimestamp ? date('YmdHis', $stripeTimestamp) : NULL;
  }

  /**
   * @param string $stripeCurrency
   *
   * @return string
   */
  public static function formatCurrency(string $stripeCurrency): string {
    return (string) mb_strtoupper($stripeCurrency);
  }

  /**
   * Convert amount to a new currency
   *
   * @param float $amount
   * @param float $exchangeRate
   * @param string $currency
   *
   * @return float
   */
  public static function currencyConversion($amount, $exchangeRate, $currency) {
    $amount = ($amount / $exchangeRate) / 100;
    // We must round to currency precision otherwise payments may fail because Contribute BAO saves but then
    // can't retrieve because it tries to use the full unrounded number when it only got saved with 2dp.
    $amount = round($amount, CRM_Utils_Money::getCurrencyPrecision($currency));
    return $amount;
  }

  /**
   * We have to map CiviCRM locales to a specific set of Stripe locales for elements to set the user language correctly.
   * Reference: https://stripe.com/docs/js/appendix/supported_locales
   * @param string $civiCRMLocale (eg. en_GB).
   *
   * @return string
   */
  public static function mapCiviCRMLocaleToStripeLocale($civiCRMLocale = '') {
    if (empty($civiCRMLocale)) {
      $civiCRMLocale = CRM_Core_I18n::getLocale();
    }
    $localeMap = [
      'en_AU' => 'en',
      'en_CA' => 'en',
      'en_GB' => 'en-GB',
      'en_US' => 'en',
      'es_ES' => 'es',
      'es_MX' => 'es-419',
      'es_PR' => 'es-419',
      'fr_FR' => 'fr',
      'fr_CA' => 'fr-CA',
      'pt_BR' => 'pt-BR',
      'pt_PT' => 'pt',
      'zh_CN' => 'zh',
      'zh_HK' => 'zh-HK',
      'zh_TW' => 'zh-TW',
    ];
    if (array_key_exists($civiCRMLocale, $localeMap)) {
      return $localeMap[$civiCRMLocale];
    }
    // Most stripe locale codes are two characters which match the first two chars
    //   of the CiviCRM locale. If it doesn't match the Stripe element will fallback
    //   to "auto"
    return substr($civiCRMLocale, 0, 2);
  }

  /**
   * Get a list of name->label for use in the settings
   *
   * @return array
   */
  public static function getListOfSupportedPaymentMethodsCheckout(): array {
    return array_column(self::getSupportedPaymentMethodsCheckout(), 'label', 'name');
  }

  /**
   * Get an array of supported StripeCheckout paymentMethods with metadata
   *
   * @return array[]
   */
  public static function getSupportedPaymentMethodsCheckout(): array {
    return [
      [
        // https://docs.stripe.com/payments/cards
        'name' => 'card',
        'label' => E::ts('Card'),
        'currencies' => ['*'],
        'recur' => TRUE,
        'setup' => TRUE,
      ],
      [
        // https://docs.stripe.com/payments/au-becs-debit
        'name' => 'au_becs_debit',
        'label' => E::ts('BECS Direct Debit payments in Australia'),
        'currencies' => ['AUD'],
        'recur' => TRUE,
        'setup' => TRUE,
      ],
      [
        // https://docs.stripe.com/payments/payment-methods/bacs-debit
        'name' => 'bacs_debit',
        'label' => E::ts('BACS Direct Debit'),
        'currencies' => ['GBP'],
        'recur' => TRUE,
        'setup' => TRUE,
      ],
      [
        // https://docs.stripe.com/payments/bancontact
        'name' => 'bancontact',
        'label' => E::ts('Bancontact'),
        'currencies' => ['EUR'],
        'recur' => TRUE,
        'setup' => TRUE,
      ],
      [
        // https://docs.stripe.com/payments/ideal/accept-a-payment
        // Requires payment_intent.succeeded / payment_intent.payment_failed
        'name' => 'ideal',
        'label' => E::ts('iDEAL'),
        'currencies' => ['EUR'],
        'recur' => TRUE,
        'setup' => TRUE,
      ],
      /*[
        // https://docs.stripe.com/payments/multibanco
        // Details of webhooks etc https://docs.stripe.com/payments/multibanco/accept-a-payment
        // Does not redirect back to site
        // Requires payment_intent.requires_action and checkout.session.async_payment_succeeded / checkout.session.async_payment_failed
        'name' => 'multibanco',
        'label' => E::ts('Multibanco'),
        'currencies' => ['EUR'],
        'recur' => FALSE,
        'setup' => FALSE,
      ],*/
      [
        // https://docs.stripe.com/payments/sepa-debit
        'name' => 'sepa_debit',
        'label' => E::ts('SEPA Direct Debit'),
        'currencies' => ['EUR'],
        'recur' => TRUE,
        'setup' => TRUE,
      ],
      [
        // https://docs.stripe.com/payments/ach-direct-debit
        'name' => 'us_bank_account',
        'label' => E::ts('ACH Direct Debit'),
        'currencies' => ['USD'],
        'recur' => TRUE,
        'setup' => TRUE,
      ],
      [
        // https://docs.stripe.com/payments/twint/accept-a-payment
        // Relies on payment_intent.succeeded or payment_intent.failed events to determine if the payment was successful.
        // @todo: Implement payment_intent.succeeded/payment_intent.failed handling
        'name' => 'twint',
        'label' => E::ts('TWINT'),
        'currencies' => ['CHF'],
        'recur' => FALSE,
        'setup' => FALSE,
      ],
    ];
  }

  /**
   * Map the Stripe Subscription Status to the CiviCRM ContributionRecur status.
   *
   * @param string $subscriptionStatus
   *
   * @return string
   */
  public static function mapSubscriptionStatusToRecurStatus(string $subscriptionStatus): string {
    $statusMap = [
      'incomplete' => 'Failed',
      'incomplete_expired' => 'Failed',
      'trialing' => 'In Progress',
      'active' => 'In Progress',
      'past_due' => 'Overdue',
      'canceled' => 'Cancelled',
      'unpaid' => 'Failed',
      'paused' => 'Pending',
    ];
    return $statusMap[$subscriptionStatus] ?? '';
  }

  /**
   * Map the Stripe Invoice Status to the CiviCRM Contribution status.
   * https://stripe.com/docs/invoicing/overview#invoice-statuses
   *
   * @param \Stripe\Invoice $invoice
   *
   * @return string
   */
  public static function mapInvoiceStatusToContributionStatus(\Stripe\Invoice $invoice): string {
    $statusMap = [
      'draft' => 'Pending',
      'open' => 'Pending',
      'paid' => 'Completed',
      'void' => 'Cancelled',
      'uncollectible' => 'Failed',
    ];
    if ($invoice->status === 'open' && $invoice->attempted && empty($invoice->next_payment_attempt)) {
      // An invoice will automatically be retried. If that fails the status will remain "open" but it has effectively failed.
      // We use attempted + next_payment_attempt to check if it will NOT be retried and then record it as Failed in CiviCRM.
      return 'Failed';
    }
    return $statusMap[$invoice->status] ?? '';
  }

}
