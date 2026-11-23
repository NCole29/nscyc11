<?php

use Civi\Test\Api4TestTrait;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 *
 * Covers the regression fixed by !374 (dev/core issue #342): a manually
 * entered discount code must be filtered by entity/price-set context, not
 * matched on the code string alone - a code scoped to one entity type must
 * not apply to another.
 *
 * cividiscount_civicrm_buildAmount() and cividiscount_civicrm_membershipTypeValues()
 * both construct a CRM_CiviDiscount_DiscountCalculator with the current
 * entity type/id and call getAvailableDiscountsWithCode() (post-fix) rather
 * than getDiscountMatchingCode() (pre-fix, unfiltered by entity). These
 * tests exercise the calculator directly with the same constructor
 * arguments those two call sites use, since that is where the actual
 * matching logic - and the bug - lives; each test uses its own unique code
 * so the process-wide static cache in BAO_Item::getValidDiscounts() (see
 * BAO/ItemTest.php) can never return stale data across tests.
 */
class CRM_CiviDiscount_DiscountCalculatorTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  use Api4TestTrait;

  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  private function createDiscount(array $values): array {
    return $this->createTestRecord('DiscountCode', $values + [
      'is_active' => TRUE,
      'amount' => 10,
      'amount_type' => 1,
    ]);
  }

  /**
   * A membership-only code must not discount an event registration.
   *
   * This is the exact scenario in dev/core#342: entered on
   * cividiscount_civicrm_buildAmount()'s Event path, the code must be
   * rejected rather than matched on its string alone.
   */
  public function testMembershipOnlyCodeRejectedForEvent(): void {
    $org = $this->createTestRecord('Contact', ['contact_type' => 'Organization']);
    $membershipType = $this->createTestRecord('MembershipType', ['member_of_contact_id' => $org['id'], 'financial_type_id.name' => 'Member Dues', 'duration_unit' => 'year', 'duration_interval' => 1]);
    $this->createDiscount([
      'code' => 'MEMBERSONLY_A',
      'memberships' => [$membershipType['id']],
    ]);

    $calculator = new CRM_CiviDiscount_DiscountCalculator('Event', 999001, NULL, 'MEMBERSONLY_A');
    $this->assertEmpty($calculator->getAvailableDiscountsWithCode());
  }

  /**
   * An event-only code must not discount a membership.
   *
   * The reverse of the scenario above, exercised via the same constructor
   * arguments cividiscount_civicrm_membershipTypeValues() uses (entity_id
   * is always NULL on that path).
   */
  public function testEventOnlyCodeRejectedForMembership(): void {
    $event = $this->createTestRecord('Event');
    $this->createDiscount([
      'code' => 'EVENTONLY_A',
      'events' => [$event['id']],
    ]);

    $calculator = new CRM_CiviDiscount_DiscountCalculator('MembershipType', NULL, NULL, 'EVENTONLY_A');
    $this->assertEmpty($calculator->getAvailableDiscountsWithCode());
  }

  /**
   * Positive control for testMembershipOnlyCodeRejectedForEvent(): an
   * event-scoped code entered for the matching event must still apply, so
   * that test isn't passing merely because the fixture never matches
   * anything.
   */
  public function testEventScopedCodeAppliesForMatchingEvent(): void {
    $event = $this->createTestRecord('Event');
    $this->createDiscount([
      'code' => 'EVENTMATCH_A',
      'events' => [$event['id']],
    ]);

    $calculator = new CRM_CiviDiscount_DiscountCalculator('Event', $event['id'], NULL, 'EVENTMATCH_A');
    $discounts = $calculator->getAvailableDiscountsWithCode();
    $this->assertCount(1, $discounts);
    $this->assertEquals('EVENTMATCH_A', $discounts['EVENTMATCH_A']['code']);
  }

  /**
   * Positive control for testEventOnlyCodeRejectedForMembership(): a
   * membership-scoped code must still apply when checked in the membership
   * context it's actually scoped to.
   */
  public function testMembershipScopedCodeAppliesForMembership(): void {
    $org = $this->createTestRecord('Contact', ['contact_type' => 'Organization']);
    $membershipType = $this->createTestRecord('MembershipType', ['member_of_contact_id' => $org['id'], 'financial_type_id.name' => 'Member Dues', 'duration_unit' => 'year', 'duration_interval' => 1]);
    $this->createDiscount([
      'code' => 'MEMBERMATCH_A',
      'memberships' => [$membershipType['id']],
    ]);

    $calculator = new CRM_CiviDiscount_DiscountCalculator('MembershipType', NULL, NULL, 'MEMBERMATCH_A');
    $discounts = $calculator->getAvailableDiscountsWithCode();
    $this->assertCount(1, $discounts);
    $this->assertEquals('MEMBERMATCH_A', $discounts['MEMBERMATCH_A']['code']);
  }

  /**
   * A code scoped to "any priceset" (pricesets => [0]) is not restricted by
   * entity type at all, so it must keep applying under either an Event or a
   * MembershipType context - guards against the fix over-restricting
   * price-set-only codes to a specific entity type.
   */
  public function testAnyPricesetCodeAppliesRegardlessOfEntityType(): void {
    $this->createDiscount([
      'code' => 'ANYPRICESET_A',
      'pricesets' => [0],
    ]);

    $eventContext = new CRM_CiviDiscount_DiscountCalculator('Event', 999002, NULL, 'ANYPRICESET_A');
    $this->assertCount(1, $eventContext->getAvailableDiscountsWithCode());

    $membershipContext = new CRM_CiviDiscount_DiscountCalculator('MembershipType', NULL, NULL, 'ANYPRICESET_A');
    $this->assertCount(1, $membershipContext->getAvailableDiscountsWithCode());
  }

}
