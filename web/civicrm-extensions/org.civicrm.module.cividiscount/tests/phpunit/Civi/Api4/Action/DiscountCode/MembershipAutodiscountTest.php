<?php
namespace Civi\Api4\Action\DiscountCode;

use Civi\Api4\DiscountCode;
use Civi\Api4\MembershipStatus;
use Civi\Test\Api4TestTrait;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 *
 * Reproduces extensions/cividiscount#335 as raised by kcristiano/KarinG: an
 * Event autodiscount configured with an advanced autodiscount filter
 * restricting it to current/new members should apply for an eligible
 * member and NOT apply for a non-member or anonymous (no contact_id) visitor.
 */
class MembershipAutodiscountTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  use Api4TestTrait;

  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function testAutodiscountOnlyAppliesToEligibleMember(): void {
    $event = $this->createTestRecord('Event');
    $statusIds = MembershipStatus::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', 'IN', ['New', 'Current'])
      ->execute()
      ->column('id');

    $this->createTestRecord('DiscountCode', [
      'code' => 'MEMBER50',
      'amount' => 50,
      'amount_type' => 1,
      'is_active' => TRUE,
      'events' => [$event['id']],
      // Mirrors the "Advanced" autodiscount filter in the report: restrict
      // to contacts with a Membership whose status is New or Current.
      'autodiscount' => ['Membership' => ['status_id' => ['IN' => $statusIds]]],
    ]);

    // CRM_CiviDiscount_BAO_Item::getValidDiscounts() memoizes its result
    // for the lifetime of the PHP process (see VerifyTest's class
    // docblock), so reset it before this class's own unfiltered fetch -
    // otherwise, if this class runs before VerifyTest in the same
    // process, this fetch would permanently freeze the shared cache to
    // just MEMBER50 and starve VerifyTest's fixtures.
    \CRM_CiviDiscount_BAO_Item::resetValidDiscountsCacheForTesting();
    \CRM_CiviDiscount_BAO_Item::getValidDiscounts();

    $org = $this->createTestRecord('Contact', ['contact_type' => 'Organization']);
    $membershipType = $this->createTestRecord('MembershipType', [
      'member_of_contact_id' => $org['id'],
      'financial_type_id.name' => 'Member Dues',
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'period_type' => 'rolling',
    ]);

    $member = $this->createTestRecord('Contact', ['contact_type' => 'Individual']);
    $this->createTestRecord('Membership', [
      'contact_id' => $member['id'],
      'membership_type_id' => $membershipType['id'],
      'status_id.name' => 'Current',
      'join_date' => date('Y-m-d'),
      'start_date' => date('Y-m-d'),
    ]);

    $nonMember = $this->createTestRecord('Contact', ['contact_type' => 'Individual']);

    $memberResult = DiscountCode::verify(FALSE)
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setContactId($member['id'])
      ->setAmount(100)
      ->execute();
    $this->assertCount(1, $memberResult, 'Eligible current member should get the autodiscount.');
    $row = $memberResult->first();
    $this->assertEquals('success', $row['verify']);
    $this->assertTrue($row['is_autodiscount']);
    $this->assertEquals(50, $row['discountedAmount']);

    $nonMemberResult = DiscountCode::verify(FALSE)
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setContactId($nonMember['id'])
      ->setAmount(100)
      ->execute();
    $this->assertCount(0, $nonMemberResult, 'Non-member must not receive the member-only autodiscount.');

    $anonymousResult = DiscountCode::verify(FALSE)
      ->setEntityType('Event')
      ->setEntityId($event['id'])
      ->setAmount(100)
      ->execute();
    $this->assertCount(0, $anonymousResult, 'Anonymous (no contact_id) visitor must not receive the member-only autodiscount.');
  }

}
