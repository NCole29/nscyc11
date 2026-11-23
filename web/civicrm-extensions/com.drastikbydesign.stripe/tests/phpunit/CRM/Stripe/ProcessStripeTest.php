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
class CRM_Stripe_ProcessStripeTest extends CRM_Stripe_TestBase {

  public function setUp(): void {
    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  /**
   * If a tracking row is still marked incomplete locally but the PaymentIntent
   * has actually succeeded at Stripe (eg. a missed/delayed webhook), the job
   * must not try to cancel it - Stripe rejects cancelling a succeeded
   * PaymentIntent, and blindly doing so used to get misread as "already
   * cancelled" and mark the row canceled even though the payment went through.
   * It should instead flag it and correct the local status to "succeeded".
   */
  public function testCancelIncompleteDoesNotCancelSucceededPaymentIntent() {
    $this->getMocksForOneOffPayment();
    $this->setupPendingContribution();

    $paymentIntentRow = StripePaymentintent::create(FALSE)
      ->setValues([
        'stripe_intent_id' => 'pi_mock',
        'contribution_id' => $this->contributionID,
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'requires_payment_method',
        'created_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
      ])
      ->execute()
      ->first();

    $result = civicrm_api3('Job', 'process_stripe', [
      'delete_old' => 0,
      'cancel_incomplete' => '-1 hour',
    ]);

    $this->assertEquals(0, $result['values']['canceled']);
    $this->assertEquals(1, $result['values']['succeeded']);

    $updatedRow = StripePaymentintent::get(FALSE)
      ->addWhere('id', '=', $paymentIntentRow['id'])
      ->execute()
      ->first();
    $this->assertEquals('succeeded', $updatedRow['status']);
  }

  /**
   * A Checkout Session tracking row uses the Session's own terminal status
   * ("complete"), not the PaymentIntent's ("succeeded") - it must be treated
   * as complete by the cleanup job, not picked up as "incomplete" and have a
   * cancel attempted against its (already succeeded) underlying PaymentIntent.
   */
  public function testCancelIncompleteIgnoresCompleteCheckoutSessionRow() {
    $this->getMocksForOneOffPayment();
    $this->setupPendingContribution();

    $paymentIntentRow = StripePaymentintent::create(FALSE)
      ->setValues([
        'identifier' => 'cs_mock',
        'stripe_intent_id' => 'pi_mock',
        'contribution_id' => $this->contributionID,
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'complete',
        'created_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
      ])
      ->execute()
      ->first();

    $result = civicrm_api3('Job', 'process_stripe', [
      'delete_old' => 0,
      'cancel_incomplete' => '-1 hour',
    ]);

    $this->assertEquals(0, $result['values']['canceled']);
    $this->assertEquals(0, $result['values']['succeeded']);

    $updatedRow = StripePaymentintent::get(FALSE)
      ->addWhere('id', '=', $paymentIntentRow['id'])
      ->execute()
      ->first();
    $this->assertEquals('complete', $updatedRow['status']);
  }

}
