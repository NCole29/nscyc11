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

/**
 * @file
 *
 * The purpose of these tests is to test this extension's code. We are not
 * focussed on testing that the StripeAPI behaves as it should, and therefore
 * we mock the Stripe API. This approach enables us to focus on our code,
 * removes external factors like network connectivity, and enables tests to
 * run quickly.
 *
 * Gotchas for developers new to phpunit's mock objects
 *
 * - once you have created a mock and called method('x') you cannot call
 *   method('x') again; you'll need to make a new mock.
 * - $this->any() refers to an argument for a with() matcher.
 * - $this->anything() refers to a method for a method() matcher.
 *
 */

/**
 * This test class deals with testing the main methods of CRM_Core_Payment_Stripe
 *
 * @group headless
 */
require_once __DIR__ . '/../../Stripe/TestBase.php';

class CRM_Core_Payment_StripeTest extends CRM_Stripe_TestBase {

  protected int $contributionRecurID;

  /**
   * This test is primarily to test the fix for
   * https://lab.civicrm.org/extensions/stripe/-/issues/440
   */
  public function testNewRecurringNewPlan() {
    $this->getMocksForRecurringPayment(FALSE);
    // Setup a recurring contribution for $this->total per month.
    $this->setupRecurringContribution();

    // Submit the payment.
    $payment_extra_params = [
      'is_recur'            => 1,
      'contributionRecurID' => $this->contributionRecurID,
      'contributionID'      => $this->contributionID,
      'frequency_unit'      => $this->contributionRecur['frequency_unit'],
      'frequency_interval'  => $this->contributionRecur['frequency_interval'],
      'installments'        => $this->contributionRecur['installments'],
    ];

    // Simulate payment
    $this->assertInstanceOf('CRM_Core_Payment_Stripe', $this->paymentObject);
    $this->doPaymentStripe($payment_extra_params);

    //
    // Check the Contribution
    // ...should be pending
    // ...its transaction ID should be our Invoice ID.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'ch_mock',
    ]);

    //
    // Check the ContributionRecur
    //
    // The subscription ID should be in both processor_id and trxn_id fields
    // We expect it to be pending
    $this->checkContribRecur([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'sub_mock',
      'processor_id'           => 'sub_mock',
    ]);
  }

  /**
   * Sets up a mocked PaymentIntent that is already in status
   * "requires_capture" (ie. the browser already confirmed it) with the
   * given $intentAmount, and configures paymentIntents->update() to
   * mimic real Stripe behaviour: it throws payment_intent_unexpected_state
   * if (and only if) the update tries to change the amount - other fields
   * (eg. description) can still be updated on a requires_capture intent.
   *
   * @return \Stripe\PaymentIntent
   *   The mock object that a successful (non-amount) update() call resolves to.
   */
  private function mockRequiresCaptureIntentForAmountMismatch(int $intentAmount) {
    $stripeClient = $this->paymentObject->stripeClient;

    // A real (not mocked) PaymentIntent object - doPayment()'s own
    // amount-mismatch check only reads ->amount/->status via __get, so no
    // method interception is needed here.
    $mockRetrievedIntent = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock',
      'status' => 'requires_capture',
      'amount' => $intentAmount,
      'latest_charge' => 'ch_mock',
    ]);

    // processPaymentIntent() additionally calls ->capture() and uses
    // empty()/isset() (via StripeObject's real __isset) on the intent, so
    // this needs to be a partial mock: only capture() is stubbed, the
    // rest of StripeObject's real property magic methods are left intact
    // (a full createMock() replaces __isset with an unconfigured stub
    // that always reports properties as unset, which breaks those
    // empty() checks).
    $mockUpdatedIntent = $this->getMockBuilder(\Stripe\PaymentIntent::class)
      ->onlyMethods(['capture'])
      ->getMock();
    $mockUpdatedIntent->refreshFrom([
      'id' => 'pi_mock',
      'status' => 'requires_capture',
      'amount' => $intentAmount,
      'latest_charge' => 'ch_mock',
    ], NULL);
    // Called (with its return value discarded) by processPaymentIntent().
    $mockUpdatedIntent->method('capture')->willReturn($mockUpdatedIntent);

    // This is what Stripe actually returns (see the reported issue) when
    // an update tries to change the amount of a PaymentIntent that
    // already requires_capture.
    $exception = \Stripe\Exception\InvalidRequestException::factory(
      "This PaymentIntent's amount could not be updated because it has a status of requires_capture. "
      . 'You may only update the amount of a PaymentIntent with one of the following statuses: '
      . 'requires_payment_method, requires_confirmation, requires_action.'
    );
    $exception->setStripeCode('payment_intent_unexpected_state');

    $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $stripeClient->paymentIntents
      ->method('retrieve')
      ->with($this->equalTo('pi_mock'))
      ->willReturn($mockRetrievedIntent);
    $stripeClient->paymentIntents
      ->method('update')
      ->with($this->equalTo('pi_mock'))
      ->willReturnCallback(function ($id, $params) use ($exception, $mockUpdatedIntent) {
        if (array_key_exists('amount', $params)) {
          throw $exception;
        }
        return $mockUpdatedIntent;
      });

    return $mockUpdatedIntent;
  }

  /**
   * Reproduces https://lab.civicrm.org/extensions/stripe/-/work_items/510
   *
   * The browser JS creates (and, because it confirms immediately, also
   * moves to status "requires_capture") a PaymentIntent using a total
   * that was rounded client-side. Due to a difference between how
   * PHP's round() and Javascript's Number.toFixed() handle a value that
   * lands exactly on a rounding boundary (eg. $20 with a 7.625% tax rate
   * gives an exact total of $21.525), the amount PHP calculates when it
   * later processes the payment can differ from the browser's amount by
   * one minor unit (cent) - 2152 vs 2153 here.
   *
   * A genuine, larger mismatch (unrelated to rounding) must still be
   * surfaced as an error rather than silently accepted - doPayment()
   * should only tolerate a 1-minor-unit difference.
   */
  public function testDoPaymentSurfacesErrorForLargerAmountMismatchAfterIntentRequiresCapture() {
    $this->getMocksForOneOffPayment();
    $this->setupPendingContribution();

    // Intent was confirmed client-side for $20.00 (2000 cents) but PHP
    // calculates a total of $25.00 (2500 cents) - a genuine 500 cent
    // discrepancy, not a 1-cent rounding artifact.
    $this->mockRequiresCaptureIntentForAmountMismatch(2000);
    $this->paymentObject->setHandleErrorThrowsException(TRUE);

    $params = [
      'payment_processor_id' => $this->paymentProcessorID,
      'amount' => 25.00,
      'paymentIntentID' => 'pi_mock',
      'email' => $this->contact['email'],
      'contactID' => $this->contact['id'],
      'contributionID' => $this->contributionID,
      'description' => 'Test from Stripe Test Code',
      'currencyID' => 'USD',
      'qfKey' => NULL,
      'entryURL' => 'http://civicrm.localhost/civicrm/test?foo',
      'query' => NULL,
      'additional_participants' => [],
    ];

    $this->expectException(\Exception::class);
    $this->expectExceptionMessageMatches('/An error occurred while processing the payment/');
    $this->paymentObject->doPayment($params);
  }

  /**
   * Reproduces https://lab.civicrm.org/extensions/stripe/-/work_items/510
   *
   * Same setup as testDoPaymentSurfacesErrorForLargerAmountMismatchAfterIntentRequiresCapture()
   * but with only a 1 minor unit (cent) discrepancy between the
   * PaymentIntent's existing amount and the PHP-recalculated amount for
   * $20 + 7.625% tax (2152 vs 2153) - the exact scenario from the
   * reported issue. This should no longer fail the transaction.
   */
  public function testDoPaymentToleratesOneCentAmountMismatchAfterIntentRequiresCapture() {
    $this->getMocksForOneOffPayment();
    $this->setupPendingContribution();

    $this->mockRequiresCaptureIntentForAmountMismatch(2152);
    $this->paymentObject->setHandleErrorThrowsException(TRUE);

    $params = [
      'payment_processor_id' => $this->paymentProcessorID,
      // 20 + (7.625% of 20) = 21.525 exactly - a rounding-boundary value
      // that PHP correctly rounds to 21.53 (2153 cents).
      'amount' => 20 + (7.625 / 100) * 20,
      'paymentIntentID' => 'pi_mock',
      'email' => $this->contact['email'],
      'contactID' => $this->contact['id'],
      'contributionID' => $this->contributionID,
      'description' => 'Test from Stripe Test Code',
      'currencyID' => 'USD',
      'qfKey' => NULL,
      'entryURL' => 'http://civicrm.localhost/civicrm/test?foo',
      'query' => NULL,
      'additional_participants' => [],
    ];

    // Prior to the fix, this call would throw ("An error occurred while
    // processing the payment") because doPayment() would attempt to
    // update the PaymentIntent's amount despite its status
    // (requires_capture) not allowing it.
    $ret = $this->paymentObject->doPayment($params);
    $this->assertEquals('ch_mock', $ret['trxn_id'] ?? NULL);
    $this->assertEquals('Completed', $ret['payment_status'] ?? NULL);
  }

  /**
   * @return void
   */
  protected function getMocksForRecurringPayment($incPlan = TRUE) {
    // Create a mock stripe client.
    $stripeClient = $this->createMock('CRM_Stripe_MockStripeClient');
    // Update our CRM_Core_Payment_Stripe object and ensure any others
    // instantiated separately will also use it.
    $this->paymentObject->setMockStripeClient($stripeClient);

    // Mock the payment methods service.
    $mockPaymentMethod = $this->createMock('Stripe\\PaymentMethod');
    $mockPaymentMethod->method('__get')
      ->will($this->returnValueMap([
        ['id', 'pm_mock'],
      ]));
    $stripeClient->paymentMethods = $this->createMock('Stripe\\Service\\PaymentMethodService');
    $stripeClient->paymentMethods
      ->method('create')
      ->willReturn($mockPaymentMethod);
    $stripeClient->paymentMethods
      ->expects($this->atLeastOnce())
      ->method('retrieve')
      ->with($this->equalTo('pm_mock'))
      ->willReturn($mockPaymentMethod);

    // Mock the Customers service
    $stripeClient->customers = $this->createMock('Stripe\\Service\\CustomerService');
    $stripeClient->customers
      ->method('create')
      ->willReturn(
        \Stripe\Customer::constructFrom(['id' => 'cus_mock', 'object' => 'customer'])
      );
    $stripeClient->customers
      ->method('retrieve')
      ->with($this->equalTo('cus_mock'))
      ->willReturn(
        \Stripe\Customer::constructFrom(['id' => 'cus_mock', 'object' => 'customer'])
      );

    // Product
    $mockProduct = \Stripe\Product::constructFrom([
      'id' => 'prod_mock',
      'name' => 'every 1 month ' . ($this->total * 100) . ' USD',
    ]);
    // Mock the products service
    $stripeClient->products = $this->createMock('Stripe\\Service\\ProductService');
    $stripeClient->products
      ->method('search')
      ->willReturn(
        \Stripe\SearchResult::constructFrom(['object' => 'search_result', 'data' => [$mockProduct], 'has_more' => FALSE, 'url' => '/v1/products/search'])
      );

    // Price
    $mockPrice = \Stripe\Price::constructFrom([
      'unit_amount' => $this->total,
      'id' => 'price_mock',
      'currency' => 'USD',
    ]);
    // Mock the prices service
    $stripeClient->prices = $this->createMock('Stripe\\Service\\PriceService');
    $stripeClient->prices
      ->method('all')
      ->willReturn(
        \Stripe\Collection::constructFrom(['object' => 'list', 'data' => [$mockPrice], 'has_more' => FALSE, 'url' => '/v1/prices'])
      );
    $stripeClient->prices
      ->method('create')
      ->willReturn(
        $mockPrice
      );

    $mockPlan = $this->createMock('Stripe\\Plan');
    $mockPlan
      ->method('__get')
      ->will($this->returnValueMap([
      ['id', 'every-1-month-' . ($this->total * 100) . '-usd'],
      ]));

    $stripeClient->plans = $this->createMock('Stripe\\Service\\PlanService');

    if ($incPlan) {
      // Normally we assume the plan exists:
      $stripeClient->plans->method('retrieve')->willReturn($mockPlan);
    }
    else {
      // For testing what happens when the plan does not exist.
      $planNotFoundException = \Stripe\Exception\InvalidRequestException::factory(
      // , null, null, null, null,
          'mock message unused untested');
      // 'resource_missing');
      $planNotFoundException->setStripeCode('resource_missing');
      $stripeClient->plans->method('retrieve')->willThrowException($planNotFoundException);
      $stripeClient->plans->method('create')->willReturn($mockPlan);

      // For these cases, there will also be a call to stripeClient->products->create.
      // But that is a static method (even though it's called on an object);
      // we cannot mock static methods. Bad bad.
      // $stripeClient->products = $this->createMock(\Stripe\Product::class);
      // $stripeClient->products->method('create')->willReturn((object) ['id' => 'mock_product_id']);
      //      $stripeClient->products = new mockProducts();
    }

    // Need a mock intent with id and status
    $mockCharge = \Stripe\Charge::constructFrom([
      'id' => 'ch_mock',
      'captured' => TRUE,
      'currency' => 'usd',
      'status' => 'succeeded',
      'balance_transaction' => 'txn_mock',
    ]);

    $mockChargesCollection = new \Stripe\Collection();
    $mockChargesCollection->data = [$mockCharge];

    $mockCharge2 = \Stripe\Charge::constructFrom([
      'id' => 'ch_mock',
      'object' => 'charge',
      'captured' => TRUE,
      'currency' => 'usd',
      'status' => 'succeeded',
      'balance_transaction' => 'txn_mock',
      'invoice' => 'in_mock',
    ]);
    $stripeClient->charges = $this->createMock('Stripe\\Service\\ChargeService');
    $stripeClient->charges
      ->method('retrieve')
      ->with($this->equalTo('ch_mock'))
      ->willReturn($mockCharge2);

    $mockPaymentIntent = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock',
      'status' => 'succeeded',
      'latest_charge' => 'ch_mock',
    ]);
    $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $stripeClient->paymentIntents
      ->method('retrieve')
      ->with($this->equalTo('pi_mock'))
      ->willReturn($mockPaymentIntent);

    $mockPaymentIntentWithAmount = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock',
      'status' => 'succeeded',
      'latest_charge' => 'ch_mock',
      'amount' => '40000',
    ]);
    $stripeClient->paymentIntents
      ->method('update')
      ->with($this->equalTo('pi_mock'))
      ->willReturn($mockPaymentIntentWithAmount);

    $mockSubscription = \Stripe\Subscription::constructFrom([
      'id' => 'sub_mock',
      'object' => 'subscription',
      'current_period_end' => time() + 60 * 60 * 24,
      'pending_setup_intent' => '',
      'latest_invoice' => [
        'id' => 'in_mock',
        'payment_intent' => $mockPaymentIntent,
      ],
    ]);
    $stripeClient->subscriptions = $this->createMock('Stripe\\Service\\SubscriptionService');
    $stripeClient->subscriptions
      ->method('create')
      ->willReturn($mockSubscription);
    $stripeClient->subscriptions
      ->method('retrieve')
      ->with($this->equalTo('sub_mock'))
      ->willReturn($mockSubscription);

    $stripeClient->balanceTransactions = $this->createMock('Stripe\\Service\\BalanceTransactionService');
    $stripeClient->balanceTransactions
      ->method('retrieve')
      ->with($this->equalTo('txn_mock'))
      ->willReturn(\Stripe\BalanceTransaction::constructFrom([
        'id' => 'txn_mock',
        'fee' => 1190, /* means $11.90 */
        'currency' => 'usd',
        'exchange_rate' => NULL,
        'object' => 'balance_transaction',
      ]));

    // $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    // todo change the status from requires_capture to ?
    //$stripeClient->paymentIntents ->method('update') ->willReturn();

    $mockInvoice = \Stripe\Invoice::constructFrom([
      'amount_due' => $this->total * 100,
    //xxx
      'charge_id' => 'ch_mock',
      'created' => time(),
      'currency' => 'usd',
      'customer' => 'cus_mock',
      'id' => 'in_mock',
      'object' => 'invoice',
      'subscription' => 'sub_mock',
    ]);
    $stripeClient->invoices = $this->createMock('Stripe\\Service\\InvoiceService');
    $stripeClient->invoices
      ->expects($this->never())
      ->method($this->anything());
  }

  /**
   * Create recurring contribition
   */
  public function setupRecurringContribution($params = []) {
    $contributionRecur = civicrm_api3('contribution_recur', 'create', array_merge([
      'financial_type_id' => $this->financialTypeID,
      'payment_instrument_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'payment_instrument_id', 'Credit Card'),
      'contact_id' => $this->contactID,
      'amount' => $this->total,
      'sequential' => 1,
      'installments' => $this->contributionRecur['installments'],
      'frequency_unit' => $this->contributionRecur['frequency_unit'],
      'frequency_interval' => $this->contributionRecur['frequency_interval'],
      'contribution_status_id' => 2,
      'payment_processor_id' => $this->paymentProcessorID,
      'is_test' => 1,
      'api.contribution.create' => [
        'total_amount' => $this->total,
        'financial_type_id' => $this->financialTypeID,
        'contribution_status_id' => 'Pending',
        'contact_id' => $this->contactID,
        'payment_processor_id' => $this->paymentProcessorID,
        'is_test' => 1,
      ],
    ], $params));
    $this->assertEquals(0, $contributionRecur['is_error']);
    $this->contributionRecurID = $contributionRecur['id'];
    $this->contributionID = $contributionRecur['values']['0']['api.contribution.create']['id'];
  }

}

/**
 * This cheeky little class is here because the 3rd party Stripe
 * code includes static methods which are untestable.
 */
class mockProducts {

  public static function create() {
    return (object) ['id' => 'mock_product_id'];
  }

}
