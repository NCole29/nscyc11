<?php
namespace Civi\CiviDiscount;

use CRM_CiviDiscount_ExtensionUtil as E;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 */
class DiscountApi4Test extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Test Api4 entity metadata
   */
  public function testEntityGet():void {
    $entityInfo = civicrm_api4('Entity', 'get', [
      'where' => [
        ['name', 'IN', ['DiscountCode', 'DiscountTrack']],
      ],
    ], 'name');
    $this->assertCount(2, $entityInfo);
    $this->assertNotEmpty($entityInfo['DiscountCode']['paths']);
    $this->assertEquals('secondary', $entityInfo['DiscountCode']['searchable']);
    $this->assertEquals('secondary', $entityInfo['DiscountTrack']['searchable']);
    $this->assertEquals('cividiscount_item', $entityInfo['DiscountCode']['table_name']);
    $this->assertEquals('cividiscount_track', $entityInfo['DiscountTrack']['table_name']);
    $this->assertEquals('CRM_CiviDiscount_DAO_Item', $entityInfo['DiscountCode']['dao']);
    $this->assertEquals('CRM_CiviDiscount_DAO_Track', $entityInfo['DiscountTrack']['dao']);
  }

}
