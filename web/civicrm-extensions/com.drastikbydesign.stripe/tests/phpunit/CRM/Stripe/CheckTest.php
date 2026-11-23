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

use Civi\Api4\StripePaymentintent;

/**
 * @group headless
 */
require_once 'TestBase.php';
class CRM_Stripe_CheckTest extends CRM_Stripe_TestBase {

  public function setUp(): void {
    parent::setUp();
    // The checks ignore test processors, so run against a live one.
    $this->createPaymentProcessor(['is_test' => 0]);
  }

  public function tearDown(): void {
    // TestBase::tearDown() doesn't truncate this table, and several tests
    // here reuse the same literal stripe_intent_id values (unique-keyed) -
    // without this, later tests would either collide on create() or pick up
    // stale rows left behind by earlier tests in the same run.
    $this->quickCleanup(['civicrm_stripe_paymentintent']);
    parent::tearDown();
  }

  private function getMessageNames(): array {
    $messages = [];
    (new \Civi\Stripe\Check())->checkRequirements($messages);
    return array_map(fn($message) => $message->getName(), $messages);
  }

  /**
   * A PaymentIntent that succeeded at Stripe over 30 minutes ago, whose
   * linked Contribution is still Pending, means the customer was charged but
   * CiviCRM never completed the order - this must be flagged to a sysadmin,
   * not just logged.
   */
  public function testOrphanedSucceededPaymentIntentIsFlagged() {
    $contribution = $this->setupPendingContribution();
    StripePaymentintent::create(FALSE)
      ->setValues([
        'stripe_intent_id' => 'pi_mock',
        'contribution_id' => $contribution['id'],
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'succeeded',
        'created_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
      ])
      ->execute();

    $this->assertContains('stripe_orphaned_succeeded_paymentintent', $this->getMessageNames());
  }

  /**
   * Payments taken by a test processor are never flagged.
   */
  public function testOrphanedTestProcessorPaymentIntentIsNotFlagged() {
    $this->setOrCreateStripePaymentProcessor();
    $contribution = $this->setupPendingContribution();
    StripePaymentintent::create(FALSE)
      ->setValues([
        'stripe_intent_id' => 'pi_mock',
        'contribution_id' => $contribution['id'],
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'succeeded',
        'created_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
      ])
      ->execute();

    $this->assertNotContains('stripe_orphaned_succeeded_paymentintent', $this->getMessageNames());
  }

  /**
   * Once the Contribution is actually Completed there's nothing to flag.
   */
  public function testCompletedContributionIsNotFlagged() {
    $contribution = $this->setupPendingContribution(['contribution_status_id' => 'Completed']);
    StripePaymentintent::create(FALSE)
      ->setValues([
        'stripe_intent_id' => 'pi_mock',
        'contribution_id' => $contribution['id'],
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'succeeded',
        'created_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
      ])
      ->execute();

    $this->assertNotContains('stripe_orphaned_succeeded_paymentintent', $this->getMessageNames());
  }

  /**
   * Deleting the Contribution is a deliberate admin action, so its succeeded
   * PaymentIntent must not keep being reported as unrecorded.
   */
  public function testDeletedContributionIsNotFlagged() {
    $contribution = $this->setupPendingContribution();
    StripePaymentintent::create(FALSE)
      ->setValues([
        'stripe_intent_id' => 'pi_mock',
        'contribution_id' => $contribution['id'],
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'succeeded',
        'created_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
      ])
      ->execute();
    \Civi\Api4\Contribution::delete(FALSE)
      ->addWhere('id', '=', $contribution['id'])
      ->execute();

    $this->assertNotContains('stripe_orphaned_succeeded_paymentintent', $this->getMessageNames());
  }

  /**
   * A payment that only just succeeded hasn't had time for the normal
   * completion paths (browser return, webhook) to catch up yet - don't flag
   * it as a problem within the grace window.
   */
  public function testRecentlySucceededPaymentIntentIsNotYetFlagged() {
    $contribution = $this->setupPendingContribution();
    StripePaymentintent::create(FALSE)
      ->setValues([
        'stripe_intent_id' => 'pi_mock',
        'contribution_id' => $contribution['id'],
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'succeeded',
        'created_date' => date('Y-m-d H:i:s'),
      ])
      ->execute();

    $this->assertNotContains('stripe_orphaned_succeeded_paymentintent', $this->getMessageNames());
  }

  /**
   * Set up two succeeded civicrm_stripe_paymentintent rows against the same
   * Contribution, mocking each PaymentIntent's amount (and, via
   * $refundedCentsByIntent, any refund against its charge), and record
   * matching Payments on the Contribution for each. Returns the recorded
   * Payments' ids, so a caller can reference one as cancelled_payment_id.
   */
  private function setupDuplicateStripePayments(int $contributionID, int $amountACents, int $amountBCents, array $recordedPayments, array $refundedCentsByIntent = []): array {
    $mockIntentA = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock_a',
      'object' => 'payment_intent',
      'status' => 'succeeded',
      'amount' => $amountACents,
      'amount_received' => $amountACents,
      'latest_charge' => \Stripe\Charge::constructFrom([
        'id' => 'ch_mock_a',
        'object' => 'charge',
        'amount_refunded' => $refundedCentsByIntent['pi_mock_a'] ?? 0,
      ]),
    ]);
    $mockIntentB = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock_b',
      'object' => 'payment_intent',
      'status' => 'succeeded',
      'amount' => $amountBCents,
      'amount_received' => $amountBCents,
      'latest_charge' => \Stripe\Charge::constructFrom([
        'id' => 'ch_mock_b',
        'object' => 'charge',
        'amount_refunded' => $refundedCentsByIntent['pi_mock_b'] ?? 0,
      ]),
    ]);
    $stripeClient = $this->paymentObject->stripeClient;
    $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $stripeClient->paymentIntents
      ->method('retrieve')
      ->will($this->returnValueMapOrDie([
        ['pi_mock_a', ['expand' => ['latest_charge']], NULL, $mockIntentA],
        ['pi_mock_b', ['expand' => ['latest_charge']], NULL, $mockIntentB],
      ]));

    foreach (['pi_mock_a', 'pi_mock_b'] as $intentID) {
      StripePaymentintent::create(FALSE)
        ->setValues([
          'stripe_intent_id' => $intentID,
          'contribution_id' => $contributionID,
          'payment_processor_id' => $this->paymentProcessorID,
          'status' => 'succeeded',
        ])
        ->execute();
    }

    $paymentIDs = [];
    foreach ($recordedPayments as $i => $amount) {
      $payment = civicrm_api3('Payment', 'create', [
        'contribution_id' => $contributionID,
        'total_amount' => $amount,
        'trxn_id' => "ch_mock_recorded_$i",
      ]);
      $paymentIDs[] = $payment['id'];
    }
    return $paymentIDs;
  }

  /**
   * A Contribution split into two genuine partial payments (eg. 30 + 30 for
   * a 60 total), both properly recorded, is not a problem.
   */
  public function testSplitPaymentsMatchingTotalAreNotFlagged() {
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['total_amount' => 60]);
    $this->setupDuplicateStripePayments($contribution['id'], 3000, 3000, [30, 30]);

    $this->assertNotContains('stripe_unrecorded_duplicate_payment', $this->getMessageNames());
  }

  /**
   * Two full-amount successful Stripe payments against one Contribution, but
   * only one of them ever became a recorded Payment - the customer was
   * charged twice and CiviCRM only knows about one of them.
   */
  public function testDuplicateUnrecordedPaymentIsFlagged() {
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['total_amount' => 60]);
    $this->setupDuplicateStripePayments($contribution['id'], 6000, 6000, [60]);

    $this->assertContains('stripe_unrecorded_duplicate_payment', $this->getMessageNames());
  }

  /**
   * Payments taken by a test processor are never flagged.
   */
  public function testDuplicateTestProcessorPaymentIsNotFlagged() {
    $this->setOrCreateStripePaymentProcessor();
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['total_amount' => 60]);
    $this->setupDuplicateStripePayments($contribution['id'], 6000, 6000, [60]);

    $this->assertNotContains('stripe_unrecorded_duplicate_payment', $this->getMessageNames());
  }

  /**
   * Two full-amount Stripe payments were taken and both recorded, but the
   * duplicate has since been refunded (at Stripe and in CiviCRM) - the
   * Contribution is now correctly reconciled and must not stay flagged.
   */
  public function testRefundedDuplicatePaymentIsNotFlagged() {
    // Recording both duplicate payments overpays the Contribution until the
    // refund lands a moment later - CiviCRM doesn't allocate FinancialItems
    // for the overpaid portion, which trips up validateAllPayments()'s
    // teardown integrity check even though nothing is actually wrong here.
    $this->isValidateFinancialsOnPostAssert = FALSE;
    $this->getMocksForOneOffPayment();
    $contribution = $this->setupPendingContribution(['total_amount' => 60]);
    $paymentIDs = $this->setupDuplicateStripePayments($contribution['id'], 6000, 6000, [60, 60], ['pi_mock_b' => 6000]);

    civicrm_api3('Payment', 'create', [
      'contribution_id' => $contribution['id'],
      'total_amount' => -60,
      'trxn_id' => 'ch_mock_recorded_refund',
      'cancelled_payment_id' => $paymentIDs[1],
    ]);

    $this->assertNotContains('stripe_unrecorded_duplicate_payment', $this->getMessageNames());
  }

}
