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

require_once 'TestBase.php';

/**
 * @group headless
 */
class CRM_Stripe_CleanupTest extends CRM_Stripe_TestBase {

  public function setUp(): void {
    parent::setUp();
  }

  public function tearDown(): void {
    StripePaymentintent::delete(FALSE)->addWhere('id', '>', 0)->execute();
    parent::tearDown();
  }

  private function createPaymentintent(array $overrides): array {
    return StripePaymentintent::create(FALSE)
      ->setValues(array_merge([
        'payment_processor_id' => $this->paymentProcessorID,
        'status' => 'requires_payment_method',
        'created_date' => date('Y-m-d H:i:s'),
      ], $overrides))
      ->execute()
      ->first();
  }

  public function testDeleteOldRemovesOnlyOldTerminalRecords() {
    $old = $this->createPaymentintent(['stripe_intent_id' => 'pi_old_succeeded', 'status' => 'succeeded', 'created_date' => date('Y-m-d H:i:s', strtotime('-4 months'))]);
    $recent = $this->createPaymentintent(['stripe_intent_id' => 'pi_recent_succeeded', 'status' => 'succeeded', 'created_date' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
    $oldButIncomplete = $this->createPaymentintent(['stripe_intent_id' => 'pi_old_incomplete', 'status' => 'requires_payment_method', 'created_date' => date('Y-m-d H:i:s', strtotime('-4 months'))]);

    $result = \Civi\Api4\Stripe::cleanup(FALSE)
      ->setDeleteOld('-3 month')
      ->setCancelIncomplete(0)
      ->execute()
      ->first();

    $this->assertEquals(1, $result['deleted']);
    $this->assertEquals(0, $result['canceled']);
    $remainingIDs = StripePaymentintent::get(FALSE)->addSelect('id')->execute()->column('id');
    $this->assertNotContains($old['id'], $remainingIDs);
    $this->assertContains($recent['id'], $remainingIDs);
    $this->assertContains($oldButIncomplete['id'], $remainingIDs);
  }

  public function testCancelIncompleteMarksAlreadyCanceledIntentAsCanceledLocally() {
    $incomplete = $this->createPaymentintent([
      'stripe_intent_id' => 'pi_already_canceled',
      'status' => 'requires_payment_method',
      'created_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    ]);

    $stripeClient = $this->createMock('CRM_Stripe_MockStripeClient');
    $this->paymentObject->setMockStripeClient($stripeClient);
    $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $stripeClient->paymentIntents
      ->method('retrieve')
      ->with($this->equalTo('pi_already_canceled'))
      ->willReturn(\Stripe\PaymentIntent::constructFrom(['id' => 'pi_already_canceled', 'status' => 'canceled']));

    $result = \Civi\Api4\Stripe::cleanup(FALSE)
      ->setDeleteOld(0)
      ->setCancelIncomplete('-1 hour')
      ->execute()
      ->first();

    $this->assertEquals(1, $result['canceled']);
    $updated = StripePaymentintent::get(FALSE)->addWhere('id', '=', $incomplete['id'])->execute()->first();
    $this->assertEquals('canceled', $updated['status']);
  }

  public function testCancelIncompleteTreatsMissingStripeIntentAsCanceled() {
    $incomplete = $this->createPaymentintent([
      'stripe_intent_id' => 'pi_missing',
      'status' => 'requires_payment_method',
      'created_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    ]);

    $stripeClient = $this->createMock('CRM_Stripe_MockStripeClient');
    $this->paymentObject->setMockStripeClient($stripeClient);
    $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $stripeClient->paymentIntents
      ->method('retrieve')
      ->with($this->equalTo('pi_missing'))
      ->willThrowException(\Stripe\Exception\InvalidRequestException::factory('No such payment_intent', 404));

    $result = \Civi\Api4\Stripe::cleanup(FALSE)
      ->setDeleteOld(0)
      ->setCancelIncomplete('-1 hour')
      ->execute()
      ->first();

    $this->assertEquals(1, $result['canceled']);
    $updated = StripePaymentintent::get(FALSE)->addWhere('id', '=', $incomplete['id'])->execute()->first();
    $this->assertEquals('canceled', $updated['status']);
  }

  public function testStripePaymentintentIdRestrictsCancelIncompleteToOneRecord() {
    $target = $this->createPaymentintent([
      'stripe_intent_id' => 'pi_target',
      'status' => 'requires_payment_method',
      'created_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    ]);
    $other = $this->createPaymentintent([
      'stripe_intent_id' => 'pi_other',
      'status' => 'requires_payment_method',
      'created_date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    ]);

    $stripeClient = $this->createMock('CRM_Stripe_MockStripeClient');
    $this->paymentObject->setMockStripeClient($stripeClient);
    $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $stripeClient->paymentIntents
      ->method('retrieve')
      ->with($this->equalTo('pi_target'))
      ->willReturn(\Stripe\PaymentIntent::constructFrom(['id' => 'pi_target', 'status' => 'canceled']));

    $result = \Civi\Api4\Stripe::cleanup(FALSE)
      ->setDeleteOld(0)
      ->setCancelIncomplete('-1 hour')
      ->setStripePaymentintentId($target['id'])
      ->execute()
      ->first();

    $this->assertEquals(1, $result['canceled']);
    $unchanged = StripePaymentintent::get(FALSE)->addWhere('id', '=', $other['id'])->execute()->first();
    $this->assertEquals('requires_payment_method', $unchanged['status']);
  }

  /**
   * The API3 Job.process_stripe entry point is kept as a deprecated shim
   * that delegates to the API4 action - assert it still produces the same
   * result shape and actually performs the work.
   */
  public function testApi3ShimDelegatesToApi4Action() {
    $old = $this->createPaymentintent(['stripe_intent_id' => 'pi_shim_old', 'status' => 'succeeded', 'created_date' => date('Y-m-d H:i:s', strtotime('-4 months'))]);

    $result = civicrm_api3('Job', 'process_stripe', [
      'delete_old' => '-3 month',
      'cancel_incomplete' => 0,
    ]);

    $this->assertEquals(1, $result['values']['deleted']);
    $remainingIDs = StripePaymentintent::get(FALSE)->addSelect('id')->execute()->column('id');
    $this->assertNotContains($old['id'], $remainingIDs);
  }

}
