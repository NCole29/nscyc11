<?php

namespace Civi\Stripe\CheckoutOption;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Civi\Api4\Contribution;
use Civi\Api4\LineItem;
use Civi\Checkout\CheckoutOptionInterface;
use CRM_Stripe_ExtensionUtil as E;
use Civi\Payment\PropertyBag;
use Civi\Payment\Exception\PaymentProcessorException;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;

/**
 * Class
 */
abstract class StripeCheckoutOptionBase implements CheckoutOptionInterface {

  /**
   * @var array
   * The PaymentProcessor record
   */
  protected array $liveConnection;

  /**
   * @var array
   * The test PaymentProcessor record
   */
  protected array $testConnection;

  public function __construct(array $liveConnection, array $testConnection) {
    $this->liveConnection = $liveConnection;
    $this->testConnection = $testConnection;
  }

  abstract public function getLabel(): string;

  protected function getConnectionDetails(bool $testMode = FALSE): array {
    return $testMode ? $this->testConnection : $this->liveConnection;
  }

  public function getFrontendLabel(): string {
    // NOTE: if we add multiple options for a given record, this key wont be enough
    // for a user to distinguish them
    return $this->connection['frontend_title'] ?? $this->getLabel();
  }

  /**
   * TODO: we should validate all the required connection details are present
   * somewhere a bit higher up.
   *
   * @throws \CRM_Core_Exception if the configuration is missing.
   */
  protected function getPublicKey(bool $testMode): string {
    $key = $this->getConnectionDetails($testMode)['user_name'];
    if (!$key) {
      $error = $testMode ? E::ts('Missing public key for Stripe test connection ID %1', [1 => $connectionId]) : E::ts('Missing public key for Stripe live connection ID %1', [1 => $this->getConnectionDetails($testMode)['id']]);
      \CRM_Core_Session::setStatus($error);
      return '';
    }
    return $key;
  }

  public function getAfformModule(): ?string {
    return NULL;
  }

  abstract public function getAfformSettings(bool $testMode): ?array;

  public function getPaymentMethod(): string {
    return 'Stripe';
  }

  public function getPaymentProcessorId(bool $testMode = FALSE): ?int {
    return $testMode ? $this->testConnection['id'] : $this->liveConnection['id'];
  }

  protected function getQuickformProcessor(bool $testMode = FALSE): \CRM_Core_Payment_Stripe {
    $id = $this->getConnectionDetails($testMode)['id'];
    return \Civi\Payment\System::singleton()->getById($id);
  }

  protected function getStripeClient(bool $testMode): \Stripe\StripeClient {
    return $this->getQuickformProcessor($testMode)->stripeClient;
  }

  protected function parseStripeException(string $op, \Exception $e): array {
    // NOTE: this function exists on the quickform processor
    // but does not depend on testMode
    return $this->getQuickformProcessor()->parseStripeException($op, $e);
  }

  /**
   * Create a Stripe Checkout Session
   *
   * @return \Stripe\Checkout\Session
   */

  /**
   * @param array $uiModeParams
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
  protected function createCheckoutSession(array $uiModeParams, bool $testMode, int $contributionId, ?string $recurIntervalUnit = NULL, int $recurIntervalCount = 1): Session {
    $contribution = Contribution::get(FALSE)
      ->addWhere('id', '=', $contributionId)
      ->addSelect('contact_id', 'invoice_id', 'amount', 'currency', 'is_test', 'source', 'contribution_recur_id')
      ->execute()
      ->single();

    // TODO: reimplement this function and/or deproperty bag
    $stripeCustomer = $this->getQuickformProcessor($testMode)->getStripeCustomer(PropertyBag::cast($contribution));
    $stripeCustomerID = $stripeCustomer->id;

    // Build the checkout session parameters
    $checkoutSessionParams = $uiModeParams + [
      'mode' => 'payment',
      'payment_method_types' => $this->getQuickformProcessor($testMode)->api->getPaymentMethodsForCurrency($contribution['currency']),
      'customer' => $stripeCustomerID,
      'line_items' => $this->getLineItems($contribution),
      // 'submit_type' => one of 'auto', pay, book, donate
      'client_reference_id' => $contribution['invoice_id'],
      'payment_intent_data' => [
        'description' => $this->getContributionDescription($contribution, $testMode),
        'capture_method' => PaymentIntent::CAPTURE_METHOD_AUTOMATIC,
      ],
    ];

    if ($recurIntervalUnit) {
      $checkoutSessionParams = $this->alterParamsForRecurring($checkoutSessionParams, $recurIntervalUnit, $recurIntervalCount);

      // Stripe creates the subscription when the customer completes Checkout, so
      // we never know the sub_... ID here to write to processor_id. Send the
      // recur ID the other way instead: Stripe copies subscription metadata onto
      // every invoice it generates, letting invoice.paid resolve the recur from
      // its own payload. Read back in CRM_Stripe_Api::getObjectParam().
      if (!empty($contribution['contribution_recur_id'])) {
        $checkoutSessionParams['subscription_data']['metadata'][\CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID]
          = (string) $contribution['contribution_recur_id'];
        if ((bool) \Civi::settings()->get('stripe_ipndebug')) {
          \Civi::log('stripe')->debug('StripeCheckout: attached '
            . \CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID . '=' . $contribution['contribution_recur_id']
            . ' to subscription_data.metadata for Contribution ' . $contributionId);
        }
      }
      elseif ((bool) \Civi::settings()->get('stripe_ipndebug')) {
        // Nothing to fall back on, so invoice.paid stays dependent on
        // checkout.session.completed winning the race.
        \Civi::log('stripe')->debug('StripeCheckout: Contribution ' . $contributionId
          . ' has no contribution_recur_id, so no subscription metadata was attached.'
          . ' Webhooks for this subscription cannot resolve the recur from their own payload.');
      }
    }

    // Allows you to alter the params passed to StripeCheckout (eg. payment_method_types)
    \CRM_Utils_Hook::alterPaymentProcessorParams($this->getQuickformProcessor($testMode), $contribution, $checkoutSessionParams);

    try {
      $checkoutSession = $this->getStripeClient($testMode)->checkout->sessions->create($checkoutSessionParams);
    }
    catch (\Exception $e) {
      $parsedError = $this->parseStripeException('createCheckout', $e);
      \CRM_Stripe_BAO_StripePaymentintent::writeRecord([
        'stripe_intent_id' => NULL,
        'contribution_id' => $contributionId,
        'payment_processor_id' => $this->getPaymentProcessorId($testMode),
        'status' => 'failed',
        'description' => $e->getMessage(),
      ]);
      throw new PaymentProcessorException($parsedError['message']);
    }

    // Record the Checkout Session for troubleshooting. We may not have a
    // stripe_intent_id yet (subscription-mode sessions only get one once the
    // first invoice is paid), so also record the session id as 'identifier'
    // so continueCheckout()/webhooks can find and update this same record.
    \CRM_Stripe_BAO_StripePaymentintent::writeRecord([
      'identifier' => $checkoutSession->id,
      'stripe_intent_id' => $checkoutSession->payment_intent ?: NULL,
      'contribution_id' => $contributionId,
      'payment_processor_id' => $this->getPaymentProcessorId($testMode),
      'status' => $checkoutSession->status,
    ]);

    \CRM_Stripe_BAO_StripeCustomer::updateMetadata(['contact_id' => $contribution['contact_id']], $this->getQuickformProcessor($testMode), $checkoutSession['customer']);

    return $checkoutSession;
  }

  /**
   * Build the description passed to Stripe for a Checkout payment/subscription.
   *
   * Uses the same format as CRM_Core_Payment_Stripe::getDescription() so that
   * the description recorded against a Stripe charge is consistent regardless
   * of whether the contribution was taken via Stripe Checkout or via the
   * quickform/PaymentElement flows.
   *
   * @param array $contribution the details on the contribution record (using Api4 keys)
   * @param bool $testMode
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  protected function getContributionDescription(array $contribution, bool $testMode): string {
    if (empty($contribution['source']) || empty($contribution['contact_id'])) {
      // getDescription() requires both a description and a contactID.
      return E::ts('CiviCRM Contribution %1', [1 => $contribution['invoice_id']]);
    }

    $propertyBag = new PropertyBag();
    $propertyBag->setContactID($contribution['contact_id']);
    $propertyBag->setDescription($contribution['source']);
    if (!empty($contribution['id'])) {
      $propertyBag->setContributionID($contribution['id']);
    }
    if (!empty($contribution['invoice_id'])) {
      $propertyBag->setInvoiceID($contribution['invoice_id']);
    }

    return $this->getQuickformProcessor($testMode)->getDescription($propertyBag);
  }

  /**
   * Fetch line items for a contribution and parse into an array suitable
   * for passing to Stripe Checkout
   *
   * @param array $contribution the details on the contribution record (using Api4 keys)
   *
   * @return array
   * @throws \Brick\Money\Exception\UnknownCurrencyException
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  protected function getLineItems(array $contribution) {
    $lineItems = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $contribution['id'])
      ->addSelect('*', 'price_field_id:label', 'price_field_value_id:label')
      ->execute();
    $currency = $contribution['currency'];
    $invoiceId = $contribution['invoice_id'];

    $stripeLineItems = [];
    $squashLineItems = FALSE;
    $lineItemSquashedAmount = 0;
    foreach ($lineItems as &$lineItem) {
      $lineItem['amount_with_tax'] = $lineItem['unit_price'] + ($lineItem['tax_amount'] ?? 0);
      if ($lineItem['amount_with_tax'] < 0) {
        // Stripe Checkout only supports positive unit_amount
        \Civi::log('stripe')->warning('StripeCheckout: LineItem amount cannot be less than zero. Squashing into one lineItem', ['lineItem' => $lineItem]);
        $squashLineItems = TRUE;
      }
      if ($lineItem['qty'] < 0) {
        // Stripe Checkout only supports positive (integer) quantity
        // By squashing lineItems we end up with a positive amount (and quantity = 1)
        // Note: We are assuming total of all lineItems > 0 and not checking that!
        $squashLineItems = TRUE;
      }
    }
    unset($lineItem);

    foreach ($lineItems as $lineItem) {
      $lineItemLabel = $lineItem['price_field_id:label'] ?: (!empty($contribution['source']) ? $contribution['source'] : E::ts('CiviCRM Contribution'));
      $lineItemDescription = $lineItem['price_field_value_id:label'] ?: E::ts('Invoice ID %1', [1 => $invoiceId]);
      $lineItemAmount = $lineItem['amount_with_tax'];

      // Stripe Checkout only supports an integer quantity. Check and adjust ratio of qty * amount if necessary
      $lineItemQuantity = filter_var($lineItem['qty'], FILTER_VALIDATE_INT);
      if ($lineItemQuantity === FALSE) {
        $lineItemQuantity = filter_var($lineItem['qty'], FILTER_VALIDATE_FLOAT);
        if ($lineItemQuantity === FALSE) {
          \Civi::log('stripe')->error('LineItem quantity must be an integer', ['lineItem' => $lineItem]);
          throw new PaymentProcessorException('LineItem quantity must be an integer!');
        }
        $lineItemAmount = $lineItemQuantity * $lineItem['amount_with_tax'];
        $lineItemLabel = $lineItemLabel . ' ' . E::ts("(Quantity: %1)", [1 => $lineItemQuantity]);
        $lineItemQuantity = 1;
        \Civi::log('stripe')->warning('StripeCheckout: LineItem quantity is a float but only integer quantity is supported. Converted ' . $lineItem['qty'] . ' to ' . $lineItemQuantity . ' and amount to ' . $lineItemAmount);
      }

      if ($squashLineItems) {
        // We need to squash lineItems
        // If $lineItemAmount or $lineItemQuantity are negative it should not matter
        //   because the total of all lineItem quantities/amounts should be positive.
        $lineItemSquashedAmount += ($lineItemAmount * $lineItemQuantity);
      }
      else {
        // Generate StripeCheckout formatted LineItem for sending to StripeCheckout
        $stripeLineItem = [
          'price_data' => [
            'currency' => $currency,
            'unit_amount' => Money::of($lineItemAmount, $currency, NULL, RoundingMode::HALF_UP)
              ->getMinorAmount()
              ->getIntegralPart(),
            'product_data' => [
              'name' => $lineItemLabel,
              'description' => $lineItemDescription,
              //'images' => ['https://example.com/t-shirt.png'],
            ],
          ],
          'quantity' => $lineItemQuantity,
        ];
        $stripeLineItems[] = $stripeLineItem;
      }
    }

    if ($squashLineItems) {
      // We have unsupported lineItems for StripeCheckout (negative amounts)
      // So we squash into a single lineItem of quantity=1 and total = sum of all lineItems.
      $stripeLineItems = [[
        'price_data' => [
          'currency' => $currency,
          'unit_amount' => Money::of($lineItemSquashedAmount, $currency, NULL, RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->getIntegralPart(),
          'product_data' => [
            'name' => !empty($contribution['source']) ? $contribution['source'] : E::ts('CiviCRM Contribution'),
            'description' => E::ts('Invoice ID %1', [1 => $invoiceId]),
          ],
        ],
        'quantity' => 1,
      ],
      ];
    }

    return $stripeLineItems ?? [];
  }

  protected function alterParamsForRecurring(array $checkoutSessionParams, string $recurIntervalUnit, int $recurIntervalCount = 1): array {
    // use subscription mode
    $checkoutSessionParams['mode'] = 'subscription';

    // use subscription data instead of payment_intent_data
    $checkoutSessionParams['subscription_data'] = $checkoutSessionParams['payment_intent_data'];
    unset($checkoutSessionParams['payment_intent_data']);
    unset($checkoutSessionParams['subscription_data']['capture_method']);

    // add the recur interval to every line item
    // TODO: support some recurring, some one off?
    foreach ($checkoutSessionParams['line_items'] as $i => $lineItem) {
      $checkoutSessionParams['line_items'][$i]['price_data']['recurring'] = [
        'interval' => $recurIntervalUnit,
        'interval_count' => $recurIntervalCount,
      ];
    }

    return $checkoutSessionParams;
  }

}
