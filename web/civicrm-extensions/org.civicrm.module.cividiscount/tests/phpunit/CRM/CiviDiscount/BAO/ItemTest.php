<?php

use Civi\Test\Api4TestTrait;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 */
class CRM_CiviDiscount_BAO_ItemTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  use Api4TestTrait;

  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * getValidDiscounts() caches its result in a function-static for the
   * lifetime of the process. A second lookup for a *different* code within
   * the same process must not silently reuse whichever code the first
   * lookup happened to be filtered by - and the underlying cache must
   * still be populated from the complete, unfiltered result so later
   * lookups by any other code can find it too.
   */
  public function testGetValidDiscountsFindsDifferentCodesInSameProcess(): void {
    $this->createTestRecord('DiscountCode', [
      'code' => 'FIRSTCODE',
      'description' => 'First',
      'amount' => 10,
      'amount_type' => 1,
      'is_active' => TRUE,
    ]);
    $this->createTestRecord('DiscountCode', [
      'code' => 'SECONDCODE',
      'description' => 'Second',
      'amount' => 20,
      'amount_type' => 1,
      'is_active' => TRUE,
    ]);

    $first = CRM_CiviDiscount_BAO_Item::getValidDiscounts('FIRSTCODE');
    $this->assertArrayHasKey('FIRSTCODE', $first, 'The first lookup (which also populates the process-wide cache) should find its own code.');

    // Before the fix, this returned the same result as the lookup above,
    // because getValidDiscounts() ignored $code on every call after the
    // very first one made in the process.
    $second = CRM_CiviDiscount_BAO_Item::getValidDiscounts('SECONDCODE');
    $this->assertArrayHasKey('SECONDCODE', $second, 'A second, different code must still be found - not silently reuse the first lookup\'s stale result.');
    $this->assertArrayNotHasKey('FIRSTCODE', $second);

    // Matching stays case-insensitive, same as the column's collation and
    // DiscountCalculator::getDiscountMatchingCode().
    $caseInsensitive = CRM_CiviDiscount_BAO_Item::getValidDiscounts('secondcode');
    $this->assertArrayHasKey('SECONDCODE', $caseInsensitive);

    // A repeat lookup of a code already queried this process must still
    // find it (served from cache rather than re-querying).
    $repeat = CRM_CiviDiscount_BAO_Item::getValidDiscounts('FIRSTCODE');
    $this->assertArrayHasKey('FIRSTCODE', $repeat);
  }

}
