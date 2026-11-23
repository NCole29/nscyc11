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
 * Tests simple recurring contribution with IPN.
 *
 * @group headless
 */
require_once 'TestBase.php';

class CRM_Stripe_IpnTest extends CRM_Stripe_TestBase {

  protected int $contributionRecurID;

  /**
   * Test creating a one-off contribution and
   * update it after creation.
   */
  public function testNewOneOffChargeSucceeded() {
    $this->mockOneOffPaymentSetup();
    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'status'       => 'succeeded',
          "captured"     => FALSE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // charge is not yet captured so contribution should remain pending
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'ch_mock',
    ]);
  }

  /**
   * Test "direct" creation (ie. contribution page) where contribution is Completed immediately.
   */
  public function testNewOneOffChargeSucceededAlreadyCompleted() {
    // Run "payment".
    $doPaymentResult = $this->mockOneOffPaymentSetup();

    // Now add a "Completed" payment per contribution page flow
    if ($doPaymentResult['payment_status'] === 'Completed') {
      civicrm_api3('Payment', 'create', [
        'trxn_id' => $doPaymentResult['trxn_id'],
        'total_amount' => $this->total,
        'fee_amount' => $doPaymentResult['fee_amount'],
        'order_reference' => $doPaymentResult['order_reference'],
        'contribution_id' => $this->contributionID,
      ]);
    }

    // Contribution should be marked as Completed
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'total_amount'           => $this->total,
    ]);
    // Financial trxn should have empty "available_on" at this point
    $this->checkFinancialTrxn([
      'Payment_details.available_on' => '',
      'fee_amount' => 11.90,
      'total_amount' => $this->total,
      'order_reference' => 'ch_mock',
      'trxn_id' => 'ch_mock',
    ],
      $this->contributionID
    );

    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'balance_transaction' => 'txn_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          "captured"     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // No change here.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'total_amount'           => $this->total,
    ]);

    // Now we should have a value for available_on
    $this->checkFinancialTrxn([
      'Payment_details.available_on' => $this->getDateinCurrentTimezone('2023-06-10 20:05:05'),
      'fee_amount' => 11.90,
      'total_amount' => $this->total,
      'order_reference' => 'ch_mock',
      'trxn_id' => 'ch_mock',
    ],
      $this->contributionID
    );
  }

  /**
   * Test completing a one-off contribution with trxn_id = paymentIntentID
   * For Stripe checkout we find the contribution using contribution.invoice_id=checkout.client_reference_id
   * Then we set the contribution.trxn_id=checkout.payment_intent_id (we don't have charge_id yet)
   * So when charge.succeeded comes in we need to match on payment_intent_id.
   *
   */
  public function testNewOneOffStripeCheckout() {
    $this->setOrCreateStripeCheckoutPaymentProcessor();
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    // Simulate payment
    $this->assertInstanceOf('CRM_Core_Payment_StripeCheckout', $this->paymentObject);

    //
    // Check the Contribution
    // ...should be pending
    // ...its transaction ID should be our Charge ID.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => '',
      'invoice_id'             => $contribution['invoice_id'],
    ]);

    // Set the new contribution to have trxn_id=pi_mock
    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'cs_mock',
          'object'       => 'checkout.session',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'pi_mock',
    ]);

    // checkout.session.completed should have recorded/updated a
    // civicrm_stripe_paymentintent row for this Checkout Session so
    // that it can be found for troubleshooting.
    $paymentintent = \Civi\Api4\StripePaymentintent::get(FALSE)
      ->addWhere('identifier', '=', 'cs_mock')
      ->execute()
      ->single();
    $this->assertEquals('pi_mock', $paymentintent['stripe_intent_id']);
    $this->assertEquals($contribution['id'], $paymentintent['contribution_id']);
    $this->assertEquals($this->paymentProcessorID, $paymentintent['payment_processor_id']);

    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock',
          'balance_transaction' => 'txn_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          "captured"     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Ensure Contribution status is updated to complete and that we now have both invoice ID and charge ID as the transaction ID.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'pi_mock,ch_mock',
      'fee_amount'             => 11.90,
    ]);
  }

  /**
   * charge.succeeded and checkout.session.completed arrive at the same time.
   * If charge.succeeded arrives first we can't match the contribution so we re-trigger it
   * once checkout.session.completed has processed.
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function testNewOneOffStripeCheckoutOutOfOrder() {
    $this->setOrCreateStripeCheckoutPaymentProcessor();
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    // Simulate payment
    $this->assertInstanceOf('CRM_Core_Payment_StripeCheckout', $this->paymentObject);

    //
    // Check the Contribution
    // ...should be pending
    // ...its transaction ID should be our Charge ID.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => '',
      'invoice_id'             => $contribution['invoice_id'],
    ]);

    // This will be ignored as it comes in before checkout.session.completed
    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock',
          'balance_transaction' => 'trx_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          'captured'     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Ensure Contribution status is updated to complete and that we now have both invoice ID and charge ID as the transaction ID.
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => '',
    ]);

    // Create dummy webhook record
    \Civi\Api4\PaymentprocessorWebhook::create(FALSE)
      ->addValue('payment_processor_id', $this->paymentProcessorID)
      ->addValue('event_id', 'ev_mock')
      ->addValue('trigger', 'charge.succeeded')
      ->addValue('status', 'success')
      ->addValue('identifier', 'pi_mock::')
      ->addValue('data', '')
      ->addValue('message', 'already processed')
      ->execute();

    // Set the new contribution to have trxn_id=pi_mock
    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'cs_mock',
          'object'       => 'checkout.session',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'pi_mock',
    ]);

    $chargeSucceededWebhook = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
      ->addWhere('identifier', 'CONTAINS', 'pi_mock')
      ->addWhere('trigger', '=', 'charge.succeeded')
      ->addWhere('status', '=', 'new')
      ->addWhere('processed_date', 'IS EMPTY')
      ->execute()
      ->first();
    $this->assertNotEmpty($chargeSucceededWebhook, 'charge.succeeded should queued for processing but is not');

    // Now trigger charge.succeeded again
    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock',
          'balance_transaction' => 'txn_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          'captured'     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Ensure Contribution status is updated to complete and that we now have both invoice ID and charge ID as the transaction ID.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'pi_mock,ch_mock',
      'fee_amount'             => 11.90,
    ]);
  }

  /**
   * Stripe redelivers events, so the queue can hold more than one row for a
   * single event_id. The dedup guard only ever processes the lowest id for an
   * event - every other row is rejected as a duplicate.
   *
   * The catch-up must therefore re-queue that lowest-id row. If it re-queues a
   * higher-id sibling instead, reprocessing rejects it and the catch-up is
   * silently a no-op, which is the case this covers.
   */
  public function testCheckoutSessionCompletedRequeuesLowestIdRowForRedeliveredEvent() {
    $this->setOrCreateStripeCheckoutPaymentProcessor();
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    // Two deliveries of the same event. created_date is set explicitly so that
    // "newest by created_date" and "lowest id" are deliberately different rows.
    $firstDelivery = \Civi\Api4\PaymentprocessorWebhook::create(FALSE)
      ->addValue('payment_processor_id', $this->paymentProcessorID)
      ->addValue('event_id', 'ev_mock')
      ->addValue('trigger', 'charge.succeeded')
      ->addValue('status', 'success')
      ->addValue('identifier', 'pi_mock::')
      ->addValue('data', '')
      ->addValue('created_date', '2026-01-01 10:00:00')
      ->execute()
      ->first();
    $secondDelivery = \Civi\Api4\PaymentprocessorWebhook::create(FALSE)
      ->addValue('payment_processor_id', $this->paymentProcessorID)
      ->addValue('event_id', 'ev_mock')
      ->addValue('trigger', 'charge.succeeded')
      ->addValue('status', 'success')
      ->addValue('identifier', 'pi_mock::')
      ->addValue('data', '')
      ->addValue('created_date', '2026-01-01 10:05:00')
      ->execute()
      ->first();
    $this->assertGreaterThan($firstDelivery['id'], $secondDelivery['id']);

    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'cs_mock',
          'object'              => 'checkout.session',
          'customer'            => 'cus_mock',
          'payment_intent'      => 'pi_mock',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    $requeued = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
      ->addSelect('id')
      ->addWhere('event_id', '=', 'ev_mock')
      ->addWhere('status', '=', 'new')
      ->execute();

    $this->assertCount(1, $requeued, 'Exactly one row should be re-queued');
    $this->assertEquals(
      $firstDelivery['id'],
      $requeued->first()['id'],
      'The lowest-id row must be re-queued - any other is rejected as a duplicate'
    );
  }

  /**
   * A retried checkout on the same Contribution overwrites trxn_id (set by
   * checkout.session.completed) before the first attempt's delayed
   * charge.succeeded webhook gets reprocessed. findContribution() must still
   * resolve the first attempt's Contribution via its own civicrm_stripe_paymentintent
   * tracking row (keyed by that attempt's own payment_intent id, never touched
   * by the second attempt), not just the now-stale trxn_id.
   */
  public function testChargeSucceededFindsContributionAfterRetryOverwritesTrxnId() {
    $this->setOrCreateStripeCheckoutPaymentProcessor();
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    // Attempt 1: first Checkout Session completes, sets trxn_id=pi_mock_a
    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'cs_mock_a',
          'object'       => 'checkout.session',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock_a',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');
    $this->checkContrib(['trxn_id' => 'pi_mock_a']);

    // Attempt 2 (retry): a second Checkout Session for the same Contribution
    // completes and overwrites trxn_id, before attempt 1's charge.succeeded
    // has been (re)processed.
    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'cs_mock_b',
          'object'       => 'checkout.session',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock_b',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');
    $this->checkContrib(['trxn_id' => 'pi_mock_b']);

    // Attempt 1's charge.succeeded now arrives - trxn_id no longer points at
    // pi_mock_a, but the Contribution must still be found (via the
    // civicrm_stripe_paymentintent row created for cs_mock_a) and completed.
    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock_a',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock_a',
          'balance_transaction' => 'txn_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          'captured'     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'pi_mock_b,ch_mock_a',
      'fee_amount'             => 11.90,
    ]);
  }

  /**
   * If a retried Checkout Session's earlier attempt succeeded at Stripe but
   * its charge.succeeded webhook is only (re)processed after a later attempt
   * has already completed the Contribution, that's still a genuine second
   * payment - it must be recorded, not silently dropped.
   */
  public function testChargeSucceededRecordsAdditionalPaymentWhenContributionAlreadyCompleted() {
    $this->setOrCreateStripeCheckoutPaymentProcessor();
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    // Attempt 1: first Checkout Session completes, sets trxn_id=pi_mock_a
    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'cs_mock_a',
          'object'       => 'checkout.session',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock_a',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Attempt 2 (retry): a second Checkout Session for the same Contribution
    // completes and overwrites trxn_id.
    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'cs_mock_b',
          'object'       => 'checkout.session',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock_b',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Attempt 2's own charge.succeeded arrives and completes the Contribution.
    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock_b',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock_b',
          'balance_transaction' => 'txn_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          'captured'     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'total_amount'           => $this->total,
    ]);
    $this->assertPaidAmount($this->total);

    // Attempt 1's charge.succeeded now (finally) arrives - the Contribution
    // it resolves to (via the civicrm_stripe_paymentintent row for cs_mock_a)
    // is already Completed by attempt 2's payment, but this is still a
    // genuinely separate successful charge and must be recorded, not dropped.
    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock_a',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock_a',
          'balance_transaction' => 'txn_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          'captured'     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Contribution stays Completed, but now has both payments recorded.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'total_amount'           => $this->total,
    ]);
    $this->assertPaidAmount($this->total * 2);

    $paymentCount = \Civi\Api4\FinancialTrxn::get(FALSE)
      ->addWhere('trxn_id', 'IN', ['ch_mock_a', 'ch_mock_b'])
      ->addWhere('is_payment', '=', TRUE)
      ->execute()
      ->count();
    $this->assertEquals(2, $paymentCount, 'Expected both charges to be recorded as separate Payments');

    // Balance transaction detail (a Payment_details custom field) must have
    // made it onto the additional payment too, not just the core columns.
    $additionalFinancialTrxn = \Civi\Api4\FinancialTrxn::get(FALSE)
      ->addSelect('custom.*')
      ->addWhere('trxn_id', '=', 'ch_mock_a')
      ->addWhere('is_payment', '=', TRUE)
      ->execute()
      ->first();
    $this->assertEquals($this->getDateinCurrentTimezone('2023-06-10 20:05:05'), $additionalFinancialTrxn['Payment_details.available_on']);
  }

  private function assertPaidAmount(float $expected): void {
    $paidAmount = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('paid_amount')
      ->addWhere('id', '=', $this->contributionID)
      ->execute()
      ->first()['paid_amount'];
    $this->assertEquals($expected, $paidAmount);
  }

  /**
   * Test creating a one-off contribution and
   * update it after creation.
   */
  public function testNewOneOffChargeCaptured() {
    $this->mockOneOffPaymentSetup();
    $success = $this->simulateEvent([
      'type'             => 'charge.captured',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'ch_mock',
          'object'       => 'charge',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'balance_transaction' => 'txn_mock',
          'created'      => time(),
          'amount'       => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'succeeded',
          'captured'     => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Ensure Contribution status is updated to complete and that we now have both invoice ID and charge ID as the transaction ID.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'fee_amount'             => 11.90,
    ]);

    // Check we set some values on the FinancialTrxn (payment)
    $this->checkFinancialTrxn([
      'Payment_details.available_on' => $this->getDateinCurrentTimezone('2023-06-10 20:05:05'),
      'fee_amount' => 11.90,
      'total_amount' => $this->total,
      'order_reference' => 'ch_mock',
      'trxn_id' => 'ch_mock',
    ],
      $this->contributionID
    );
  }

  /**
   * Test creating a one-off contribution and
   * update it after creation.
   */
  public function testNewOneOffChargeRefundedFull() {
    $doPaymentResult = $this->mockOneOffPaymentSetup();

    if ($doPaymentResult['payment_status'] === 'Completed') {
      civicrm_api3('Payment', 'create', [
        'trxn_id' => $doPaymentResult['trxn_id'],
        'total_amount' => $this->total,
        'fee_amount' => $doPaymentResult['fee_amount'],
        'order_reference' => $doPaymentResult['order_reference'],
        'contribution_id' => $this->contributionID,
      ]);
    }

    $success = $this->simulateEvent([
      'type'             => 'charge.refunded',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'              => 'ch_mock',
          'object'          => 'charge',
          'customer'        => 'cus_mock',
          'charge'          => 'ch_mock',
          'created'         => time(),
          'amount_refunded' => $this->total * 100,
          'status'          => 'succeeded',
          "captured"        => TRUE,
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Ensure Contribution status is updated to complete and that we now have both invoice ID and charge ID as the transaction ID.
    $this->checkContrib([
      'contribution_status_id' => 'Refunded',
      'trxn_id'                => 'ch_mock,re_mock',
    ]);
  }

  /**
   * Unlike charge succeeded, charge failed is processed.
   */
  public function testNewOneOffStripeCheckoutChargeFailed() {
    $this->setOrCreateStripeCheckoutPaymentProcessor();
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    // Simulate payment
    $this->assertInstanceOf('CRM_Core_Payment_StripeCheckout', $this->paymentObject);

    //
    // Check the Contribution
    // ...should be pending
    // ...its transaction ID should be our Charge ID.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => '',
      'invoice_id'             => $contribution['invoice_id'],
    ]);

    // Set the new contribution to have trxn_id=pi_mock
    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'cs_mock',
          'object'       => 'checkout.session',
          'customer'     => 'cus_mock',
          'payment_intent' => 'pi_mock',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'pi_mock',
    ]);

    $success = $this->simulateEvent([
      'type'             => 'charge.failed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'ch_mock',
          'object'              => 'charge',
          'amount'              => $this->total * 100,
          'amount_captured'     => $this->total * 100,
          'captured'            => TRUE,
          'balance_transaction' => 'txn_mock',
          'customer'            => 'cus_mock',
          'payment_intent'      => 'pi_mock',
          'created'             => time(),
          'failure_message'     => 'Mocked failure',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    //
    // Ensure Contribution is marked Failed, with the reason, and that the
    // ContributionRecur is not changed from Pending.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Failed',
      'trxn_id'                => 'ch_mock',
      'cancel_reason'          => 'Mocked failure',
    ]);
  }

  /**
   * Test that starting a FormBuilder Stripe Embedded Checkout session
   * records a civicrm_stripe_paymentintent row (keyed by the Checkout
   * Session id, since we don't have a stripe_intent_id for subscriptions
   * until the first invoice is paid).
   */
  public function testEmbeddedCheckoutStartRecordsPaymentintent() {
    $this->setOrCreateStripePaymentProcessor();
    $this->getMocksForOneOffPayment();
    $this->getMocksForCheckoutSession(['status' => 'open', 'payment_intent' => 'pi_mock']);
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    $checkoutOption = new \Civi\Stripe\CheckoutOption\StripeEmbeddedCheckout([], $this->paymentProcessor);
    $session = new \Civi\Checkout\CheckoutSession($contribution['id'], 'stripe_embedded_checkout');
    $checkoutOption->startCheckout($session);

    $paymentintent = \Civi\Api4\StripePaymentintent::get(FALSE)
      ->addWhere('identifier', '=', 'cs_mock')
      ->execute()
      ->single();
    $this->assertEquals('pi_mock', $paymentintent['stripe_intent_id']);
    $this->assertEquals($contribution['id'], $paymentintent['contribution_id']);
    $this->assertEquals($this->paymentProcessorID, $paymentintent['payment_processor_id']);
    $this->assertEquals('open', $paymentintent['status']);
  }

  /**
   * Test that returning from a FormBuilder Stripe Embedded Checkout session
   * (continueCheckout) updates the same civicrm_stripe_paymentintent row
   * created when the session was started, rather than creating a duplicate.
   */
  public function testEmbeddedCheckoutContinueUpdatesPaymentintent() {
    $this->setOrCreateStripePaymentProcessor();
    $this->getMocksForOneOffPayment();
    $this->getMocksForCheckoutSession(['status' => 'open', 'payment_intent' => 'pi_mock']);
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    $checkoutOption = new \Civi\Stripe\CheckoutOption\StripeEmbeddedCheckout([], $this->paymentProcessor);
    $session = new \Civi\Checkout\CheckoutSession($contribution['id'], 'stripe_embedded_checkout');
    $checkoutOption->startCheckout($session);

    // Simulate the browser returning to continueCheckout() once Stripe
    // shows the session as complete.
    $this->getMocksForCheckoutSession(['status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_mock']);
    $_REQUEST['session_id'] = 'cs_mock';
    $checkoutOption->continueCheckout($session);
    unset($_REQUEST['session_id']);

    $paymentintents = \Civi\Api4\StripePaymentintent::get(FALSE)
      ->addWhere('identifier', '=', 'cs_mock')
      ->execute();
    $this->assertCount(1, $paymentintents, 'continueCheckout() should update the existing row, not create a duplicate');
    $this->assertEquals('complete', $paymentintents->single()['status']);
  }

  /**
   * Stripe's own post-payment redirect for Embedded Checkout is opaque
   * client-side and has no fallback if it silently fails to fire (eg. a
   * backgrounded/suspended tab) for payment methods that don't need a
   * redirect (eg. cards). We handle those ourselves via
   * stripeEmbeddedCheckout.component.js's onComplete handler instead, so
   * starting a session must ask for "if_required" rather than the default
   * "always" - and, unlike "never", still pass return_url, since Stripe
   * still needs it for payment methods that genuinely require a redirect
   * (eg. Bancontact/iDEAL).
   */
  public function testEmbeddedCheckoutStartUsesIfRequiredRedirect() {
    $this->setOrCreateStripePaymentProcessor();
    $this->getMocksForOneOffPayment();
    $mockCheckoutSession = \Stripe\Checkout\Session::constructFrom([
      'id' => 'cs_mock',
      'object' => 'checkout.session',
      'customer' => 'cus_mock',
      'payment_intent' => 'pi_mock',
      'status' => 'open',
      'payment_status' => 'unpaid',
      'client_secret' => 'cs_mock_secret',
      'url' => 'https://checkout.stripe.com/c/pay/cs_mock',
    ]);
    $stripeClient = $this->paymentObject->stripeClient;
    $stripeClient->checkout = new stdClass();
    $stripeClient->checkout->sessions = $this->createMock('Stripe\\Service\\Checkout\\SessionService');
    $stripeClient->checkout->sessions
      ->method('create')
      ->with($this->callback(fn($params) => ($params['redirect_on_completion'] ?? NULL) === 'if_required'
        && !empty($params['return_url'])))
      ->willReturn($mockCheckoutSession);

    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);
    $checkoutOption = new \Civi\Stripe\CheckoutOption\StripeEmbeddedCheckout([], $this->paymentProcessor);
    $session = new \Civi\Checkout\CheckoutSession($contribution['id'], 'stripe_embedded_checkout');
    // No exception/assertion failure from the mock's ->with() callback above
    // means Stripe was asked for the right redirect behaviour.
    $checkoutOption->startCheckout($session);
  }

  /**
   * ...and the client needs return_url to navigate back itself from its
   * onComplete handler once the checkout actually completes.
   */
  public function testEmbeddedCheckoutStartReturnsReturnUrlForClientFallback() {
    $this->setOrCreateStripePaymentProcessor();
    $this->getMocksForOneOffPayment();
    $this->getMocksForCheckoutSession(['status' => 'open', 'payment_intent' => 'pi_mock']);
    $contribution = $this->setupPendingContribution(['invoice_id' => md5(uniqid(mt_rand(), TRUE))]);

    $checkoutOption = new \Civi\Stripe\CheckoutOption\StripeEmbeddedCheckout([], $this->paymentProcessor);
    $session = new \Civi\Checkout\CheckoutSession($contribution['id'], 'stripe_embedded_checkout');
    $checkoutOption->startCheckout($session);

    // CheckoutSession::tokenise() signs an 'exp' claim taken from the wall
    // clock, so each call mints a different token and the two URLs cannot be
    // compared as strings - crossing a second boundary between them is what
    // made this test flaky. Advancing time here forces that difference every
    // run, so a regression to comparing raw URLs fails immediately.
    \CRM_Utils_Time::setTime(date('YmdHis', \CRM_Utils_Time::time() + 1));
    try {
      $responseItems = $session->getResponseItems();
      $returnUrl = $responseItems['stripe_embedded_checkout']['return_url'];

      // Same landing URL apart from the token itself.
      $tokenPattern = '/([?&]session=)[^&]*/';
      $this->assertEquals(
        preg_replace($tokenPattern, '$1', $session->getLandingUrl()),
        preg_replace($tokenPattern, '$1', $returnUrl)
      );

      // ...and the token it does carry is for this checkout session.
      parse_str((string) parse_url($returnUrl, PHP_URL_QUERY), $query);
      $restored = \Civi\Checkout\CheckoutSession::restoreFromToken($query['session']);
      $this->assertEquals($contribution['id'], $restored->getContributionId());
    }
    finally {
      \CRM_Utils_Time::resetTime();
    }
  }

  /**
   * Test creating a recurring contribution and
   * update it after creation. @todo The membership should also be updated.
   */
  public function testNewRecurringInvoicePaymentSucceeded() {
    $this->mockRecurringPaymentSetup();
    $success = $this->simulateEvent([
      'type'             => 'invoice.payment_succeeded',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock',
          'object'       => 'invoice',
          'subscription' => 'sub_mock',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'      => 'paid',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Ensure Contribution status is updated to complete and that we now have both invoice ID and charge ID as the transaction ID.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'fee_amount'             => 11.90,
    ]);
    $this->checkContribRecur(['contribution_status_id' => 'In Progress']);
  }

  /**
   * Test creating a recurring contribution and
   * update it after creation. @todo The membership should also be updated.
   */
  public function testNewRecurringInvoicePaid() {
    $this->mockRecurringPaymentSetup();
    $success = $this->simulateEvent([
      'type'             => 'invoice.paid',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock',
          'object'       => 'invoice',
          'subscription' => 'sub_mock',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Ensure Contribution status is updated to complete and that we now have both invoice ID and charge ID as the transaction ID.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'fee_amount'             => 11.90,
    ]);
    $this->checkContribRecur(['contribution_status_id' => 'In Progress']);
  }

  /**
   * A Checkout Session in subscription mode carries no payment_intent - Stripe
   * puts it on the generated invoice instead. doCheckoutSessionCompleted passes
   * whatever it found to getPaymentMethodForContribution(), so on every
   * subscription signup that is an empty string.
   *
   * Without a guard that becomes paymentIntents->retrieve(''), which costs a
   * Stripe API round-trip and then throws "The resource ID cannot be null or
   * whitespace" - caught and logged as an error, so the signup succeeds but
   * every one leaves an error in the log and the Contribution keeps the default
   * payment instrument rather than the actual method.
   *
   * Assert the lookup is skipped entirely rather than attempted and caught.
   */
  public function testCheckoutSessionCompletedForSubscriptionDoesNotLookUpEmptyPaymentIntent() {
    $this->mockRecurringPaymentSetup();

    $contribution = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $this->contributionID)
      ->addSelect('invoice_id')
      ->execute()
      ->first();

    // A fresh mock: the setup helpers already stubbed retrieve() on the shared
    // one, and a mock's method() cannot be re-declared.
    $this->paymentObject->stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $this->paymentObject->stripeClient->paymentIntents
      ->expects($this->never())
      ->method('retrieve');

    $success = $this->simulateEvent([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'cs_mock',
          'object'              => 'checkout.session',
          'customer'            => 'cus_mock',
          'client_reference_id' => $contribution['invoice_id'],
          // Subscription mode: invoice + subscription, and no payment_intent.
          'invoice'             => 'in_mock',
          'subscription'        => 'sub_mock',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');
  }

  /**
   * Stripe Checkout + recurring: invoice.paid arrives before
   * checkout.session.completed, so processor_id is still a placeholder hash.
   *
   * mockRecurringPaymentSetup() uses the Elements flow and asserts
   * processor_id === 'sub_mock', so run it and then rewind the two fields
   * Checkout would not yet have populated (processor_id, trxn_id).
   *
   * The metadata must resolve the recur and the payment must land on the
   * existing contribution, leaving exactly one on the recur.
   */
  public function testRecurringStripeCheckoutInvoicePaidBeforeCheckoutSessionCompleted() {
    $this->mockRecurringPaymentSetup();

    // Rewind to the state Stripe Checkout leaves us in before
    // checkout.session.completed has been processed.
    $placeholderProcessorID = md5(uniqid(mt_rand(), TRUE));
    \Civi\Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $this->contributionRecurID)
      ->addValue('processor_id', $placeholderProcessorID)
      ->execute();
    \Civi\Api4\Contribution::update(FALSE)
      ->addWhere('id', '=', $this->contributionID)
      ->addValue('trxn_id', NULL)
      ->execute();

    // Precondition: the subscription ID resolves to nothing, and there is
    // exactly one (Pending) contribution on the recur.
    $this->assertEmpty(
      \Civi\Api4\ContributionRecur::get(FALSE)
        ->addWhere('processor_id', '=', 'sub_mock')
        ->addWhere('is_test', 'IN', [TRUE, FALSE])
        ->execute()
        ->first(),
      'Precondition failed: recur should not yet be linked to sub_mock'
    );
    $this->getContributionsAndAssertCount(1);

    $success = $this->simulateEvent([
      'type'             => 'invoice.paid',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock',
          'object'       => 'invoice',
          'subscription' => 'sub_mock',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
          // The first invoice of the subscription - so this pays for the
          // Contribution that already exists, not a new instalment.
          'billing_reason' => \Stripe\Invoice::BILLING_REASON_SUBSCRIPTION_CREATE,
          // The snapshot Stripe copies from the subscription onto each invoice.
          'subscription_details' => [
            'metadata' => [
              \CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID => (string) $this->contributionRecurID,
            ],
          ],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // The metadata fallback should have resolved the recur and backfilled
    // processor_id, so every later event resolves via the normal lookup.
    $this->checkContribRecur([
      'contribution_status_id' => 'In Progress',
      'processor_id'           => 'sub_mock',
    ]);

    // Must land on the existing contribution: the fall-through path calls
    // Contribution.repeattransaction, which always INSERTs.
    $contributions = $this->getContributionsAndAssertCount(1);
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'fee_amount'             => 11.90,
    ], $contributions[0]);
  }

  /**
   * Guard against the first-invoice fix over-reaching. Adoption is only correct
   * for the first invoice: if it fired for renewals too, each renewal would
   * overwrite the same Contribution instead of adding to the series.
   *
   * Same state as the test above, but billing_reason says renewal, so a new
   * Contribution must be created regardless.
   */
  public function testRecurringInvoicePaidForRenewalDoesNotAdoptInitialContribution() {
    $this->mockRecurringPaymentSetup();

    $placeholderProcessorID = md5(uniqid(mt_rand(), TRUE));
    \Civi\Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $this->contributionRecurID)
      ->addValue('processor_id', $placeholderProcessorID)
      ->execute();
    \Civi\Api4\Contribution::update(FALSE)
      ->addWhere('id', '=', $this->contributionID)
      ->addValue('trxn_id', NULL)
      ->execute();

    $this->getContributionsAndAssertCount(1);

    $success = $this->simulateEvent([
      'type'             => 'invoice.paid',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock',
          'object'       => 'invoice',
          'subscription' => 'sub_mock',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
          // A renewal, NOT the first invoice.
          'billing_reason' => \Stripe\Invoice::BILLING_REASON_SUBSCRIPTION_CYCLE,
          'subscription_details' => [
            'metadata' => [
              \CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID => (string) $this->contributionRecurID,
            ],
          ],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // A renewal adds to the series: the pre-existing Pending Contribution must
    // still be there untouched, alongside the new one.
    $contributions = $this->getContributionsAndAssertCount(2);
    $this->assertEquals(
      $this->contributionID,
      $contributions[0]['id'],
      'The original Contribution should not have been adopted by a renewal invoice'
    );
  }

  /**
   * Adoption must not depend on trxn_id being empty: checkout.session.completed
   * can stamp trxn_id within the same second as invoice.paid arrives.
   *
   * The Contribution here carries a trxn_id that findContribution() cannot match
   * (it names a different invoice), so adoption is the only correct route.
   * Exactly one Contribution must exist afterwards.
   */
  public function testRecurringInvoicePaidAdoptsInitialContributionEvenWhenTrxnIDAlreadySet() {
    $this->mockRecurringPaymentSetup();

    $placeholderProcessorID = md5(uniqid(mt_rand(), TRUE));
    \Civi\Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $this->contributionRecurID)
      ->addValue('processor_id', $placeholderProcessorID)
      ->execute();
    // Stamped, but not with anything this event's findContribution() can match.
    \Civi\Api4\Contribution::update(FALSE)
      ->addWhere('id', '=', $this->contributionID)
      ->addValue('trxn_id', 'in_stale_mock')
      ->execute();

    $this->getContributionsAndAssertCount(1);

    $success = $this->simulateEvent([
      'type'             => 'invoice.paid',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock',
          'object'       => 'invoice',
          'subscription' => 'sub_mock',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
          'billing_reason' => \Stripe\Invoice::BILLING_REASON_SUBSCRIPTION_CREATE,
          'subscription_details' => [
            'metadata' => [
              \CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID => (string) $this->contributionRecurID,
            ],
          ],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Adopted, not duplicated.
    $contributions = $this->getContributionsAndAssertCount(1);
    $this->assertEquals(
      $this->contributionID,
      $contributions[0]['id'],
      'The existing Contribution should have been adopted despite carrying a trxn_id'
    );
  }

  /**
   * Test invoice.paid where the invoice has no top-level `subscription` (eg.
   * Stripe consolidated multiple subscriptions onto this invoice) but the
   * subscription ID is still available on the invoice's line item.
   */
  public function testNewRecurringInvoicePaidSubscriptionFromLineItem() {
    $this->mockRecurringPaymentSetup();
    $success = $this->simulateEvent([
      'type'             => 'invoice.paid',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock',
          'object'       => 'invoice',
          'subscription' => NULL,
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
          'lines'        => [
            'object' => 'list',
            'data'   => [
              ['id' => 'il_mock', 'object' => 'line_item', 'subscription' => 'sub_mock'],
            ],
          ],
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'fee_amount'             => 11.90,
    ]);
    $this->checkContribRecur(['contribution_status_id' => 'In Progress']);
  }

  /**
   * Test invoice.paid processed via the "no webhook secret configured" path
   * (CRM_Core_Payment_StripeIPN::setVerifyData(TRUE)): the event data we get
   * from events->retrieve() is missing `charge` and `subscription` entirely
   * (as happens when Stripe renders it at eg. a basil account-default API
   * version, which Stripe uses regardless of our pinned webhook version and
   * ignores our Stripe-Version header for when re-retrieving an Event).
   * CRM_Core_Payment_StripeIPN::retrieveFreshObject() should re-fetch the
   * actual Invoice object directly (which does honour our pinned version)
   * and use that instead, recovering both fields for all downstream
   * processing.
   */
  public function testNewRecurringInvoicePaidViaVerifiedEventRecoversMissingFields() {
    $this->mockRecurringPaymentSetup();

    // This is what a real invoices->retrieve() call returns: a complete
    // Invoice, unlike the sparse/incomplete one Stripe rendered onto the
    // Event at the account's basil-or-later default API version.
    $mockFreshInvoice = \Stripe\Invoice::constructFrom([
      'id'           => 'in_mock',
      'object'       => 'invoice',
      'charge'       => 'ch_mock',
      'subscription' => 'sub_mock',
      'customer'     => 'cus_mock',
      'created'      => time(),
      'amount_due'   => $this->total * 100,
      'currency'     => 'usd',
      'status'       => 'paid',
    ]);
    $this->paymentObject->stripeClient->invoices = $this->createMock('Stripe\\Service\\InvoiceService');
    $this->paymentObject->stripeClient->invoices
      ->method('retrieve')
      ->with($this->equalTo('in_mock'))
      ->willReturn($mockFreshInvoice);

    $success = $this->simulateVerifiedEvent([
      'type'             => 'invoice.paid',
      'id'               => 'evt_mock',
    // ?
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock',
          'object'       => 'invoice',
          'charge'       => NULL,
          'subscription' => NULL,
          'customer'     => 'cus_mock',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'fee_amount'             => 11.90,
    ]);
    $this->checkContribRecur(['contribution_status_id' => 'In Progress']);
  }

  /**
   * Test creating a recurring contribution and
   * the handling of charge.succeeded
   *
   * This should be a no-op event; charge.succeeded events are only processed for
   * one-offs, though it does fire for recurrings, hence the test.
   */
  public function testNewRecurringChargeSucceededAreIgnored() {

    $this->mockRecurringPaymentSetup();
    $success = $this->simulateEvent([
      'type'             => 'charge.succeeded',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'ch_mock',
          'object'              => 'charge',
          'amount'              => $this->total * 100,
          'amount_captured'     => $this->total * 100,
          'captured'            => TRUE,
          'balance_transaction' => 'txn_mock',
          'invoice'             => 'in_mock',
          'customer'            => 'cus_mock',
          'created'             => time(),
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    //
    // Ensure Contribution and recur records remain as-was.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'ch_mock',
    ]);
    $this->checkContribRecur(['contribution_status_id' => 'Pending']);
  }

  /**
   * Unlike charge succeeded, charge failed is processed.
   */
  public function testNewRecurringChargeFailed() {
    $this->mockRecurringPaymentSetup();

    $success = $this->simulateEvent([
      'type'             => 'charge.failed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'ch_mock',
          'object'              => 'charge',
          'amount'              => $this->total * 100,
          'amount_captured'     => $this->total * 100,
          'captured'            => TRUE,
          'balance_transaction' => 'txn_mock',
          'invoice'             => 'in_mock',
          'customer'            => 'cus_mock',
          'created'             => time(),
          'failure_message'     => 'Mocked failure',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    //
    // Ensure Contribution is marked Failed, with the reason, and that the
    // ContributionRecur is not changed from Pending.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Failed',
      'trxn_id'                => 'in_mock',
      'cancel_reason'          => 'Mocked failure',
    ]);
    $this->checkContribRecur(['contribution_status_id' => 'Pending']);

    $contributionLog = \Civi\Api4\ContributionLog::get(FALSE)
      ->setOrderBy(['id' => 'DESC'])
      ->execute()
      ->first();
    $this->assertNotEmpty($contributionLog);
    $expectedValues = [
      'identifier' => 'ch_mock',
      'message' => 'Mocked failure',
      'level' => 'error',
    ];
    foreach ($expectedValues as $key => $expectedValue) {
      $this->assertEquals($expectedValue, $contributionLog[$key]);
    }
  }

  /**
   *
   * @see https://stripe.com/docs/billing/invoices/overview#invoice-status-transition-endpoints-and-webhooks
   */
  public function testNewRecurringInvoicePaymentFailed() {

    $this->mockRecurringPaymentSetup();

    $success = $this->simulateEvent([
      'id'               => 'evt_mock',
      'object'           => 'event',
      'type'             => 'invoice.payment_failed',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'in_mock',
          'object'              => 'invoice',
          'charge'              => 'ch_mock',
          'amount_due'          => $this->total * 100,
          'amount_paid'         => 0,
          'currency'            => 'usd',
          'customer'            => 'cus_mock',
          'created'             => time(),
          'status'              => 'uncollectible',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    //
    // Ensure Contribution is marked Failed, with the reason, and that the
    // ContributionRecur is not changed from Pending.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Failed',
      'trxn_id'                => 'in_mock',
    ]);
    $this->checkContribRecur(['contribution_status_id' => 'Pending']);
  }

  /**
   * What about the next payments in a recurring?
   *
   * - Repeats initial testNewRecurringInvoicePaymentSucceeded, then
   *
   * - Creates invoice.finalized event: this should create a Pending
   *   Contribution with its trxn_id set to the new invoice ID.
   *
   * - Creates invoice.payment_succeeded: which should complete the Contribution
   *   and should update its trxn_id, appending the new charge ID
   *
   */
  public function testRecurringInvoiceFinalizedChronological() {

    // Initial payment comes in...
    $this->testNewRecurringInvoicePaymentSucceeded();

    list ($mockCharge1, $mockCharge2, $mockInvoice2, $balanceTransaction2) = $this->getMocksForRecurringInvoiceFinalized();
    $success = $this->simulateEvent([
      'type'             => 'invoice.finalized',
      'id'               => 'evt_mock_2',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => $mockInvoice2,
      ],
    ], TRUE);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Recur should still be In Progress.
    $this->checkContribRecur(['contribution_status_id' => 'In Progress']);

    // We should have a new contribution.
    $contributions = $this->getContributionsAndAssertCount(2);
    $contrib2 = $contributions[1];
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'in_mock_2',
    ], (int) $contrib2['id']);

    // Now the charge succeeds on this invoice.
    $this->simulateEvent([
      'type'             => 'invoice.payment_succeeded',
      'id'               => 'evt_mock_3',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock_2',
          'object'       => 'invoice',
          'subscription' => 'sub_mock',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock_2',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
        ],
      ],
    ], TRUE);
    // Check the contribution was updated.
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'in_mock_2,ch_mock_2',
      'fee_amount'             => 11.90,
    ],
      (int) $contrib2['id']
    );

    // Check we set some values on the FinancialTrxn (payment)
    $this->checkFinancialTrxn([
      'Payment_details.available_on' => $this->getDateinCurrentTimezone('2023-06-10 20:05:05'),
      'fee_amount' => 11.90,
      'total_amount' => $this->total,
      'order_reference' => 'in_mock_2',
      'trxn_id' => 'ch_mock_2',
    ],
      (int) $contrib2['id']
    );
  }

  /**
   * It's possible that the payment_succeeded event comes in before finalized.
   *
   * - Repeats initial testNewRecurringInvoicePaymentSucceeded, then
   *
   * - Creates invoice.payment_succeeded: which should complete the Contribution
   *   and should update its trxn_id, appending the new charge ID
   *
   * - Creates invoice.finalized event: this should basically not do anything.
   *
   *
   */
  public function testRecurringInvoiceFinalizedNotChronological() {

    // Initial payment comes in...
    $this->testNewRecurringInvoicePaymentSucceeded();
    list ($mockCharge1, $mockCharge2, $mockInvoice2, $balanceTransaction2) = $this->getMocksForRecurringInvoiceFinalized();

    // Simulate payment_succeeded before we have had a invoice finalized.
    $success = $this->simulateEvent([
      'type'             => 'invoice.payment_succeeded',
      'id'               => 'evt_mock_2',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'           => 'in_mock_2',
          'object'       => 'invoice',
          'subscription' => 'sub_mock',
          'customer'     => 'cus_mock',
          'charge'       => 'ch_mock_2',
          'created'      => time(),
          'amount_due'   => $this->total * 100,
          'currency'     => 'usd',
          'status'       => 'paid',
        ],
      ],
    ], TRUE);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // We should have a new, Completed contribution.
    $contributions = $this->getContributionsAndAssertCount(2);
    $contrib2 = $contributions[1];
    // Check the new contribution
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'in_mock_2,ch_mock_2',
      'fee_amount'             => 11.90,
    ], $contrib2);

    // Now trigger invoice.finalized. We expect that it does nothing?
    $success = $this->simulateEvent([
      'type'             => 'invoice.finalized',
      'id'               => 'evt_mock_3',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => $mockInvoice2,
      ],
    ], TRUE);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Recur should still be In Progress.
    $this->checkContribRecur(['contribution_status_id' => 'In Progress']);

    // We should still have just 2 contribs.
    $contributions = $this->getContributionsAndAssertCount(2);

    // Our 2nd contribution should still be Completed and have the same trxn_id
    $contrib2 = $contributions[1];
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'in_mock_2,ch_mock_2',
      'fee_amount'             => 11.90,
    ], $contrib2);

  }

  /**
   * Tests situation when the initial recurring payment came in OK,
   * but the next one fails.
   *
   * 1. Repeats basic test on first successful payment to get the database set up.
   *
   * 2. Simulates invoice.finalized. This is the normal case and creates a Pending Contribution.
   *
   * 3. Simulates invoice.payment_failed: this time the Contribution created in
   *    (2) should be updated to Failed.
   *
   */
  public function testRecurringInvoicePaymentFailedThenSucceeds() {

    // Initial payment comes in...
    $this->testNewRecurringInvoicePaymentSucceeded();

    $createdTimestamp = time();
    //
    // Now test if we get invoice.finalized first.
    //
    // To do this we'll need a new invoice and a new charge.
    // and pending balance transaction
    $mockInvoice2 = \Stripe\Invoice::constructFrom([
      'id'           => 'in_mock_2',
      'object'       => 'invoice',
      'amount_due'   => $this->total * 100,
      'currency'     => 'usd',
      'charge'       => 'ch_mock_2',
      'subscription' => 'sub_mock',
      'customer'     => 'cus_mock',
      'created'      => time(),
    ]);
    $mockCharge2 = \Stripe\Charge::constructFrom([
      'id'                  => 'ch_mock_2',
      'object'              => 'charge',
      'balance_transaction' => 'txn_mock_2',
      'currency'     => 'usd',
      'amount'              => $this->total * 100,
      'subscription' => 'sub_mock',
      'customer'     => 'cus_mock',
      'created'      => $createdTimestamp,
      'failure_message' => 'payment failed',
      'status'       => 'failed',
    ]);
    $balanceTransaction2 = \Stripe\BalanceTransaction::constructFrom([
      'id'            => 'txn_mock_2',
      'object'        => 'balance_transaction',
      'amount'        => $this->total * 100,
      'created'       => time(),
      'currency'      => 'usd',
      'exchange_rate' => NULL,
      'fee'           => 1190, /* means $11.90 */
      'status'        => 'pending',
      'type'          => 'charge',
    ]);
    $this->paymentObject->stripeClient->balanceTransactions = $this->createMock('Stripe\\Service\\BalanceTransactionService');
    $this->paymentObject->stripeClient->balanceTransactions
      ->method('retrieve')
      ->with($this->equalTo('txn_mock_2'))
      ->willReturn($balanceTransaction2);

    $this->paymentObject->stripeClient->charges = $this->createMock('Stripe\\Service\\ChargeService');
    $this->paymentObject->stripeClient->charges
      ->method('retrieve')
      ->will($this->returnValueMapOrDie([
        ['ch_mock_2', NULL, NULL, $mockCharge2],
      ]));

    //
    // Simulate invoice.finalized
    //
    $success = $this->simulateEvent([
      'type'             => 'invoice.finalized',
      'id'               => 'evt_mock_3',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => $mockInvoice2,
      ],
    ], TRUE);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Recur should still be In Progress.
    $this->checkContribRecur(['contribution_status_id' => 'In Progress']);

    // We should have 2 contribs.
    // The 2nd one should be Pending.
    $contributions = $this->getContributionsAndAssertCount(2);
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id' => 'in_mock_2',
    ], $contributions[1]);

    //
    // Now simulate a failed invoice again. (normal flow for a failed invoice)
    //
    $success = $this->simulateEvent([
      'id'               => 'evt_mock_4',
      'object'           => 'event',
      'type'             => 'invoice.payment_failed',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'in_mock_2',
          'object'              => 'invoice',
          'charge'              => 'ch_mock_2',
          'subscription'        => 'sub_mock',
          'amount_due'          => $this->total * 100,
          'amount_paid'         => 0,
          'currency'            => 'usd',
          'customer'            => 'cus_mock',
          'created'             => $createdTimestamp,
          'status'              => 'uncollectible',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // The 2nd contribution should be Failed
    $contributions = $this->getContributionsAndAssertCount(2);
    $this->checkContrib([
      'contribution_status_id' => 'Failed',
      'trxn_id' => 'in_mock_2',
      'cancel_reason' => 'payment failed',
    ], $contributions[1]);

    //
    // Now simulate an invoice.payment_succeeded - e.g. a 2nd charge attempt worked.
    //
    $mockCharge3 = \Stripe\Charge::constructFrom([
      'id'                  => 'ch_mock_3',
      'object'              => 'charge',
      'balance_transaction' => 'txn_mock_3',
      'currency'     => 'usd',
      'amount'              => $this->total * 100,
      'subscription'        => 'sub_mock',
      'customer'            => 'cus_mock',
      'created'             => time(),
      'status'              => 'succeeded',
    ]);
    $balanceTransaction2 = \Stripe\BalanceTransaction::constructFrom([
      'id'            => 'txn_mock_3',
      'object'        => 'balance_transaction',
      'amount'        => $this->total * 100,
      'created'       => time(),
      'currency'      => 'usd',
      'exchange_rate' => NULL,
      'fee'           => 1190, /* means $11.90 */
      'status'        => 'available',
      'type'          => 'charge',
    ]);
    $this->paymentObject->stripeClient->balanceTransactions = $this->createMock('Stripe\\Service\\BalanceTransactionService');
    $this->paymentObject->stripeClient->balanceTransactions
      ->method('retrieve')
      ->with($this->equalTo('txn_mock_3'))
      ->willReturn($balanceTransaction2);

    $this->paymentObject->stripeClient->charges = $this->createMock('Stripe\\Service\\ChargeService');
    $this->paymentObject->stripeClient->charges
      ->method('retrieve')
      ->will($this->returnValueMapOrDie([
        ['ch_mock_3', NULL, NULL, $mockCharge3],
      ]));

    $success = $this->simulateEvent([
      'id'               => 'evt_mock_5',
      'object'           => 'event',
      'type'             => 'invoice.payment_succeeded',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
    // still same invoice
          'id'          => 'in_mock_2',
          'object'      => 'invoice',
    // different charge
          'charge'      => 'ch_mock_3',
          'subscription' => 'sub_mock',
          'amount_due'  => $this->total * 100,
          'amount_paid' => 0,
          'currency'    => 'usd',
          'customer'    => 'cus_mock',
          'created'     => time(),
          'status'      => 'paid',
        ],
      ],
    ]);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // The 2nd contribution should now be Completed and have the invoice and the successful charge as its trxn_id
    $contributions = $this->getContributionsAndAssertCount(2);
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id' => 'in_mock_2,ch_mock_3',
      'fee_amount' => 11.90,
    ], $contributions[1]);
  }

  /**
   * Tests situation when the initial recurring payment came in OK,
   * but the subscription is then deleted.
   */
  public function testRecurringDeletedAfterInitialSuccess() {
    // Initial payment comes in...
    $this->testNewRecurringInvoicePaymentSucceeded();

    $cancelTimestamp = time();
    $mockSubscription = \Stripe\Subscription::constructFrom([
      'id'                 => 'sub_mock',
      'object'             => 'subscription',
      'current_period_end' => time() + 60 * 60 * 24,
      'status'             => 'cancelled',
      // Note US? spelling of cancelled
      'canceled_at'        => $cancelTimestamp,
      'plan' => [
        'amount' => $this->total,
        'currency' => 'usd',
      ],
    ]);
    $stripeClient = $this->paymentObject->stripeClient;
    $stripeClient->subscriptions = $this->createMock('Stripe\\Service\\SubscriptionService');
    $stripeClient->subscriptions
      ->method('retrieve')
      ->will($this->returnValueMapOrDie([
        ['sub_mock', NULL, NULL, $mockSubscription],
      ]));
    //
    // Now test if we get customer.subscription.deleted .
    //
    $success = $this->simulateEvent([
      'type'             => 'customer.subscription.deleted',
      'id'               => 'evt_mock_2',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => $mockSubscription,
      ],
    ], TRUE);
    $this->assertEquals(TRUE, $success, 'IPN did not return OK');

    // Recur should be Cancelled.
    $this->checkContribRecur([
      'contribution_status_id' => 'Cancelled',
      'cancel_date'            => date('Y-m-d H:i:s', $cancelTimestamp),
    ]);

    // We should still have 1 contrib which should be unaffected.
    $this->getContributionsAndAssertCount(1);
    $this->checkContrib([
      'contribution_status_id' => 'Completed',
      'trxn_id'                => 'ch_mock',
      'fee_amount'             => 11.90,
    ]);
  }

  /**
   * Run the webhook/ipn
   *
   * @return bool
   *   Whether it was successful (nb. FALSE might be valid where we want
   *   stripe to resend something again later).
   */
  public function ipn($event, $verifyRequest = TRUE, $exceptionOnFailure = FALSE) {
    $ipnClass = new CRM_Core_Payment_StripeIPN($this->paymentObject);

    if ($exceptionOnFailure) {
      // We don’t expect failure, so ensure exceptions are not caught.
      $ipnClass->exceptionOnFailure = $exceptionOnFailure;
    }
    $ipnClass->setEventID($event->id);
    if (!$ipnClass->setEventType($event->type)) {
      // We don't handle this event
      return FALSE;
    }
    $ipnClass->setVerifyData($verifyRequest);
    if (!$verifyRequest) {
      $ipnClass->setData($event->data);
    }
    $ipnClass->setExceptionMode(FALSE);

    return $ipnClass->processWebhookEvent()->ok;
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

  /**
   * Returns an array of arrays of contributions.
   */
  protected function getContributionsAndAssertCount(int $expectedCount):array {
    $contributions = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('contribution_recur_id', '=', $this->contributionRecurID)
      ->addWhere('is_test', '=', 1)
      ->addOrderBy('id')
      ->execute();
    $this->assertEquals($expectedCount, $contributions->count());
    return $contributions->getArrayCopy();
  }

  /**
   * DRY code
   */
  protected function getMocksForRecurringInvoiceFinalized() :array {
    $common = [
      'subscription' => 'sub_mock',
      'customer'     => 'cus_mock',
      'created'      => time(),
    ];
    $mockCharge1 = \Stripe\Charge::constructFrom($common + [
      'id'                  => 'ch_mock',
      'object'              => 'charge',
      'balance_transaction' => 'txn_mock',
      'currency'            => 'usd',
      'amount'              => $this->total * 100,
      'status'              => 'succeeded',
    ]);
    $mockCharge2 = \Stripe\Charge::constructFrom($common + [
      'id'                  => 'ch_mock_2',
      'object'              => 'charge',
      'balance_transaction' => 'txn_mock_2',
      'currency'            => 'usd',
      'amount'              => $this->total * 100,
      'status'              => 'succeeded',
    ]);
    $mockInvoice2 = \Stripe\Invoice::constructFrom($common + [
      'id'           => 'in_mock_2',
      'object'       => 'invoice',
      'amount_due'   => $this->total * 100,
      'currency'     => 'usd',
      'charge'       => 'ch_mock_2',
    ]);
    $balanceTransaction2 = \Stripe\BalanceTransaction::constructFrom([
      'id'            => 'txn_mock_2',
      'object'        => 'balance_transaction',
      'amount'        => $this->total * 100,
      'created'       => time(),
      'currency'      => 'usd',
      'exchange_rate' => NULL,
      'fee'           => 1190, /* means $11.90 */
      'status'        => 'available',
      'type'          => 'charge',
    // 2023-06-10 21:05:05
      'available_on'  => '1686427505',
    ]);

    $this->paymentObject->stripeClient->balanceTransactions = $this->createMock('Stripe\\Service\\BalanceTransactionService');
    $this->paymentObject->stripeClient->balanceTransactions
      ->method('retrieve')
      ->with($this->equalTo('txn_mock_2'))
      ->willReturn($balanceTransaction2);

    $this->paymentObject->stripeClient->charges = $this->createMock('Stripe\\Service\\ChargeService');
    $this->paymentObject->stripeClient->charges
      ->method('retrieve')
      ->will($this->returnValueMapOrDie([
        ['ch_mock', NULL, NULL, $mockCharge1],
        ['ch_mock_2', NULL, NULL, $mockCharge2],
      ]));

    return [$mockCharge1, $mockCharge2, $mockInvoice2, $balanceTransaction2];
  }

  /**
   * @return void
   */
  protected function getMocksForRecurringPayment() {
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
    $stripeClient->plans
      ->method('retrieve')
      ->willReturn($mockPlan);

    // Need a mock intent with id and status
    $mockCharge = $this->createMock('Stripe\\Charge');
    $mockCharge
      ->method('__get')
      ->will($this->returnValueMap([
        ['id', 'ch_mock'],
        ['captured', TRUE],
        ['currency', 'usd'],
        ['status', 'succeeded'],
        ['balance_transaction', 'txn_mock'],
      ]));

    $mockChargesCollection = new \Stripe\Collection();
    $mockChargesCollection->data = [$mockCharge];

    // This also serves charges->retrieve(..., ['expand' => ['invoice']])
    // calls (see StripeIPN::setInputParameters()'s charge->invoice
    // expansion) - real Stripe would return the full, current charge from
    // this call (with 'invoice' expanded), so 'customer'/'failure_message'
    // need to be present here too, matching what individual event fixtures
    // for 'ch_mock' assume.
    $mockCharge = \Stripe\Charge::constructFrom([
      'id' => 'ch_mock',
      'object' => 'charge',
      'captured' => TRUE,
      'currency' => 'usd',
      'status' => 'succeeded',
      'balance_transaction' => 'txn_mock',
      'customer' => 'cus_mock',
      'failure_message' => 'Mocked failure',
      'invoice' => [
        'id' => 'in_mock',
        'object' => 'invoice',
        'subscription' => 'sub_mock',
      ],
    ]);
    $stripeClient->charges = $this->createMock('Stripe\\Service\\ChargeService');
    $stripeClient->charges
      ->method('retrieve')
      ->with($this->equalTo('ch_mock'))
      ->willReturn($mockCharge);

    $mockPaymentIntent = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock',
      'object' => 'payment_intent',
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
      'object' => 'payment_intent',
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
   * DRY code. Sets up the database as it would be after a recurring contrib
   * has been set up with Stripe.
   *
   * Results in a pending ContributionRecur and a pending Contribution record.
   *
   * The following mock Stripe IDs strings are used:
   *
   * - pm_mock   PaymentMethod
   * - pi_mock   PaymentIntent
   * - cus_mock  Customer
   * - ch_mock   Charge
   * - txn_mock  Balance transaction
   * - sub_mock  Subscription
   */
  protected function mockRecurringPaymentSetup() {
    $this->getMocksForRecurringPayment();

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
   *
   */
  protected function returnValueMapOrDie($map): ValueMapOrDie {
    return new ValueMapOrDie($map);
  }

  /**
   * Simulate an event being sent from Stripe and processed by our IPN code.
   *
   * @var array|Stripe\Event $eventData
   * @var bool $exceptionOnFailure
   *
   * @return bool result from ipn()
   */
  protected function simulateEvent($eventData, $exceptionOnFailure = TRUE) {
    // Mock Event service.
    $stripeClient = $this->paymentObject->stripeClient;
    $stripeClient->events = $this->createMock('Stripe\\Service\\EventService');

    $mockEvent = \Stripe\Event::constructFrom($eventData);
    $stripeClient->events
      ->method('all')
      ->willReturn(\Stripe\Collection::constructFrom(['object' => 'list', 'data' => [$mockEvent], 'has_more' => FALSE, 'url' => '/v1/events']));
    $stripeClient->events
      ->method('retrieve')
      ->with($this->equalTo($eventData['id']))
      ->willReturn($mockEvent);

    // Process it with the IPN/webhook
    return $this->ipn($mockEvent, FALSE, $exceptionOnFailure);
  }

  /**
   * Like simulateEvent() but exercises the "no webhook secret configured"
   * path (CRM_Core_Payment_StripeIPN::setVerifyData(TRUE)): the class
   * fetches the event live via events->retrieve() instead of trusting a
   * pre-set/trusted payload, then (per
   * CRM_Core_Payment_StripeIPN::retrieveFreshObject()) re-fetches the
   * actual object by its type/id - callers should mock whichever
   * stripeClient service that re-fetch will hit (eg. ->invoices,
   * ->charges) before calling this.
   *
   * @param array $eventData As per simulateEvent().
   * @param bool $exceptionOnFailure
   *
   * @return bool
   */
  protected function simulateVerifiedEvent($eventData, $exceptionOnFailure = TRUE) {
    // Mock Event service.
    $stripeClient = $this->paymentObject->stripeClient;
    $stripeClient->events = $this->createMock('Stripe\\Service\\EventService');

    $mockEvent = \Stripe\Event::constructFrom($eventData);
    $stripeClient->events
      ->method('retrieve')
      ->with($this->equalTo($eventData['id']))
      ->willReturn($mockEvent);

    // Process it with the IPN/webhook, forcing the "verify" (live re-fetch) path.
    return $this->ipn($mockEvent, TRUE, $exceptionOnFailure);
  }

}
