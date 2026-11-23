<?php
namespace Civi\Api4\Action\DiscountCode;

use Civi\Api4\DiscountCode;
use Civi\Test\Api4TestTrait;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 *
 * CRM_CiviDiscount_BAO_Item::getValidDiscounts() memoizes its result in a
 * class-static cache that is populated once per PHP process and never
 * invalidated per discount code. In a multi-test run that means the very
 * first DiscountCalculator lookup permanently determines which codes any
 * *later* test can ever find, regardless of what's actually in the DB by
 * then - so naively creating fixtures test-by-test makes later "code
 * found" assertions pass or fail for the wrong reason, and makes results
 * depend on which test class PHPUnit happens to run first.
 *
 * To keep every scenario below genuinely exercising real matching logic
 * regardless of test order, primeDiscountCache() resets that shared cache,
 * creates every code-lookup-dependent fixture up front (the first time any
 * test's setUp() runs), and forces one unfiltered fetch to populate the
 * cache with this class's fixtures only. Per-request, *live* checks that
 * Verify/DiscountCalculator make on every call regardless of the cache
 * (getErrorMessage(), the per-contact usage count in
 * filterDiscountsByUserMax(), entity ID matching) are unaffected by this
 * and are exercised per-test as normal.
 */
class VerifyTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  use Api4TestTrait;

  private static bool $primed = FALSE;
  private static int $save10Id;
  private static int $onceOnlyId;
  private static int $restrictedEventId;
  private static int $autoDiscountEventId;

  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  protected function setUp(): void {
    parent::setUp();
    if (!self::$primed) {
      $this->primeDiscountCache();
      self::$primed = TRUE;
    }
  }

  private function primeDiscountCache(): void {
    \CRM_CiviDiscount_BAO_Item::resetValidDiscountsCacheForTesting();

    self::$restrictedEventId = $this->createTestRecord('Event')['id'];
    self::$autoDiscountEventId = $this->createTestRecord('Event')['id'];

    self::$save10Id = $this->createDiscount([
      'code' => 'SAVE10',
      'amount' => 10,
      'amount_type' => 1,
      'events' => [0],
    ])['id'];
    $this->createDiscount([
      'code' => 'FLAT15',
      'amount' => 15,
      'amount_type' => 2,
      'events' => [0],
    ]);
    self::$onceOnlyId = $this->createDiscount([
      'code' => 'ONCEONLY',
      'amount' => 10,
      'amount_type' => 1,
      'events' => [0],
      'count_user_max' => 1,
    ])['id'];
    $this->createDiscount([
      'code' => 'EVENTONLY',
      'amount' => 10,
      'amount_type' => 1,
      'events' => [self::$restrictedEventId],
    ]);
    $this->createDiscount([
      'code' => 'AUTO20',
      'amount' => 20,
      'amount_type' => 1,
      'events' => [self::$autoDiscountEventId],
      'autodiscount' => ['Contact' => []],
    ]);

    // One unfiltered fetch, which is what actually populates the
    // process-wide static cache - everything above becomes visible to
    // every DiscountCalculator instance for the rest of this run.
    \CRM_CiviDiscount_BAO_Item::getValidDiscounts();
  }

  private function createDiscount(array $values): array {
    return $this->createTestRecord('DiscountCode', $values + [
      'is_active' => TRUE,
    ]);
  }

  public function testPercentageCodeDiscountApplies(): void {
    $event = $this->createTestRecord('Event');

    $result = DiscountCode::verify(FALSE)
      ->setDiscountCode('SAVE10')
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setAmount(100)
      ->execute();

    $this->assertCount(1, $result);
    $row = $result->first();
    $this->assertEquals('success', $row['verify']);
    $this->assertFalse($row['is_autodiscount']);
    $this->assertEquals(self::$save10Id, $row['discount_id']);
    $this->assertEquals('SAVE10', $row['discount_code']);
    $this->assertEquals('SAVE10', $row['discountCode']);
    $this->assertEquals(90, $row['discountedAmount']);
    $this->assertEquals(90, $row['total_after_discount']);
    $this->assertEquals(10, $row['amount_to_discount']);
  }

  public function testFixedAmountCodeDiscountApplies(): void {
    $event = $this->createTestRecord('Event');

    $row = DiscountCode::verify(FALSE)
      ->setDiscountCode('FLAT15')
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setAmount(100)
      ->execute()
      ->first();

    $this->assertEquals('success', $row['verify']);
    $this->assertEquals(85, $row['discountedAmount']);
  }

  public function testUnknownDiscountCodeFails(): void {
    $event = $this->createTestRecord('Event');

    $row = DiscountCode::verify(FALSE)
      ->setDiscountCode('DOES-NOT-EXIST')
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setAmount(100)
      ->execute()
      ->first();

    $this->assertEquals('failed', $row['verify']);
    $this->assertEquals('The discount code you entered is invalid.', $row['reason']);
  }

  public function testGlobalUsageCapExhaustedFails(): void {
    // Deliberately NOT part of the shared priming set: its exhausted
    // count_use/count_max is baked into the row at fetch time, so it must
    // exist only within this test's own transaction to be evaluated live
    // (see class docblock).
    $event = $this->createTestRecord('Event');
    $this->createDiscount([
      'code' => 'MAXEDOUT',
      'amount' => 10,
      'amount_type' => 1,
      'events' => [0],
      'count_max' => 1,
      'count_use' => 1,
    ]);

    $row = DiscountCode::verify(FALSE)
      ->setDiscountCode('MAXEDOUT')
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setAmount(100)
      ->execute()
      ->first();

    $this->assertEquals('failed', $row['verify']);
    $this->assertEquals('There are not enough uses remaining for this code.', $row['reason']);
  }

  public function testPerUserCapExhaustedFails(): void {
    $event = $this->createTestRecord('Event');
    $contact = $this->createTestRecord('Contact', ['contact_type' => 'Individual']);
    // ONCEONLY (count_user_max = 1) is already in the shared cache as an
    // otherwise-valid code; per-user usage is always checked live via a
    // join from DiscountTrack to DiscountCode.code, so a *local* row with
    // the same code (this test's own transaction - its row id doesn't
    // need to match the primed one, only its `code` does) satisfies both
    // that join and the FK on DiscountTrack.item_id, and creating this
    // contact's prior use here (not during priming) is what actually
    // exercises filterDiscountsByUserMax().
    $onceOnly = $this->createDiscount([
      'code' => 'ONCEONLY',
      'amount' => 10,
      'amount_type' => 1,
      'events' => [0],
      'count_user_max' => 1,
    ]);
    $this->createTestRecord('DiscountTrack', [
      'item_id' => $onceOnly['id'],
      'contact_id' => $contact['id'],
      'entity_table' => 'civicrm_participant',
      'entity_id' => 1,
    ]);

    $row = DiscountCode::verify(FALSE)
      ->setDiscountCode('ONCEONLY')
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setContactId($contact['id'])
      ->setAmount(100)
      ->execute()
      ->first();

    $this->assertEquals('failed', $row['verify']);
    $this->assertEquals('You have already used this code.', $row['reason']);
  }

  public function testCodeNotApplicableToDifferentEventFails(): void {
    // EVENTONLY (primed, restricted to self::$restrictedEventId) - calling
    // with a *different* event proves the entity filter genuinely excludes
    // it, rather than the code simply being absent from the cache.
    $otherEvent = $this->createTestRecord('Event');

    $row = DiscountCode::verify(FALSE)
      ->setDiscountCode('EVENTONLY')
      ->setEntityType('Event')
      ->setEntityId($otherEvent['id'])
      ->setAmount(100)
      ->execute()
      ->first();

    $this->assertEquals('failed', $row['verify']);
    $this->assertEquals('The discount code you entered is invalid.', $row['reason']);
  }

  public function testAutodiscountAppliesWithoutCode(): void {
    $contact = $this->createTestRecord('Contact', ['contact_type' => 'Individual']);

    // AUTO20 (primed) applies to any contact, but only for
    // self::$autoDiscountEventId - the same event this call targets.
    $result = DiscountCode::verify(FALSE)
      ->setEntityType('Event')
      ->setEntityId(self::$autoDiscountEventId)
      ->setContactId($contact['id'])
      ->setAmount(50)
      ->execute();

    $this->assertCount(1, $result);
    $row = $result->first();
    $this->assertEquals('success', $row['verify']);
    $this->assertTrue($row['is_autodiscount']);
    $this->assertEquals(40, $row['discountedAmount']);
  }

  public function testNoCodeAndNoAutodiscountReturnsNoRows(): void {
    $contact = $this->createTestRecord('Contact', ['contact_type' => 'Individual']);
    // A different event than AUTO20's - so no autodiscount applies here.
    $unrelatedEvent = $this->createTestRecord('Event');

    $result = DiscountCode::verify(FALSE)
      ->setEntityType('Event')
      ->setEntityId($unrelatedEvent['id'])
      ->setContactId($contact['id'])
      ->setAmount(50)
      ->execute();

    // No code and no matching autodiscount is a silent no-op, not a failure row.
    $this->assertCount(0, $result);
  }

  public function testMissingRequiredEntityTypeThrows(): void {
    // entityType/entityId/amount are nullable-typed properties defaulting
    // to NULL (not plain typed-with-no-default), so they're always safely
    // readable and ValidateFieldsSubscriber's own `=== NULL` check is what
    // catches the missing value - producing the API's normal friendly
    // required-parameter exception rather than a raw \Error.
    $this->expectException(\CRM_Core_Exception::class);
    $this->expectExceptionMessage('Parameter "entityType" is required.');

    DiscountCode::verify(FALSE)
      ->setEntityId(1)
      ->setAmount(50)
      ->execute();
  }

}
