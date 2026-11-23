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
 * @group headless
 */
require_once 'TestBase.php';
class CRM_Stripe_ApiTest extends CRM_Stripe_TestBase {

  public function setUp(): void {
    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  /**
   * getDetailsFromBalanceTransactionByChargeID('') must return [] without
   * making a Stripe API call - an empty charge ID means there's nothing to
   * look up (eg. the charge_id was never recovered from Stripe), and
   * calling charges->retrieve('') would just error. Pass NULL for the
   * payment processor: if the guard is removed, this test errors instead of
   * silently passing, because the method would try to call a method on NULL.
   */
  public function testGetDetailsFromBalanceTransactionByChargeIDReturnsEmptyForEmptyChargeID() {
    $api = new \Civi\Stripe\Api(NULL);
    $this->assertEquals([], $api->getDetailsFromBalanceTransactionByChargeID(''));
  }

  /**
   * The metadata key is a wire-format contract with Stripe, not an internal
   * detail: it is written onto the Stripe subscription at Checkout Session
   * creation and read back, potentially years later, off invoices Stripe
   * generates from that subscription.
   *
   * Renaming the constant would therefore orphan every subscription already
   * created with the old key - those recurring contributions would silently
   * stop resolving via the metadata fallback. Pin the literal so that change
   * has to be deliberate.
   */
  public function testMetadataRecurIDKeyIsStable() {
    $this->assertSame(
      'civicrm_contribution_recur_id',
      \CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID,
      'Renaming this key orphans every Stripe subscription created with the old one'
    );
  }

  /**
   * Stripe copies the subscription's metadata onto every invoice generated
   * from it, at invoice.subscription_details.metadata.
   */
  public function testGetRecurIDFromInvoice() {
    $invoice = \Stripe\Invoice::constructFrom([
      'id'                   => 'in_mock',
      'object'               => 'invoice',
      'subscription_details' => [
        'metadata' => [\CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID => '42'],
      ],
    ]);
    $this->assertSame('42', \CRM_Stripe_Api::getObjectParam('subscription_metadata_recur_id', $invoice));
  }

  /**
   * Read directly off a Subscription object, as delivered by
   * customer.subscription.updated / .deleted.
   */
  public function testGetRecurIDFromSubscription() {
    $subscription = \Stripe\Subscription::constructFrom([
      'id'       => 'sub_mock',
      'object'   => 'subscription',
      'metadata' => [\CRM_Stripe_Api::METADATA_CONTRIBUTION_RECUR_ID => '42'],
    ]);
    $this->assertSame('42', \CRM_Stripe_Api::getObjectParam('subscription_metadata_recur_id', $subscription));
  }

  /**
   * Subscriptions created before this feature existed - or created outside our
   * Checkout flow - carry no such metadata. That must degrade to empty rather
   * than erroring, so the caller falls back to the processor_id lookup.
   *
   * @dataProvider objectsWithoutRecurMetadata
   */
  public function testGetRecurIDReturnsEmptyWhenMetadataAbsent(array $objectData) {
    $stripeObject = \Stripe\Util\Util::convertToStripeObject($objectData, []);
    $this->assertSame('', \CRM_Stripe_Api::getObjectParam('subscription_metadata_recur_id', $stripeObject));
  }

  public static function objectsWithoutRecurMetadata(): array {
    return [
      'invoice with no subscription_details at all' => [
        [
          'id'     => 'in_mock',
          'object' => 'invoice',
        ],
      ],
      'invoice with subscription_details but no metadata' => [
        [
          'id'                   => 'in_mock',
          'object'               => 'invoice',
          'subscription_details' => ['metadata' => []],
        ],
      ],
      'invoice with unrelated metadata only' => [
        [
          'id'                   => 'in_mock',
          'object'               => 'invoice',
          'subscription_details' => ['metadata' => ['something_else' => 'x']],
        ],
      ],
      'subscription with no metadata' => [
        [
          'id'     => 'sub_mock',
          'object' => 'subscription',
        ],
      ],
    ];
  }

}
