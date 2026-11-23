<?php

namespace Civi\Api4\Action\DiscountCode;

use Civi\Api4\Generic\AbstractAction;
use CRM_CiviDiscount_ExtensionUtil as E;
use Civi\Api4\Generic\Result;

/**
 * Verify action can be used to check, whether a discount code is existing and valid.
 *
 * Validation covers the following scenarios:
 *
 * - discount code is active
 * - maximum usage count
 * - within allowed date range
 * - eligible for the given event (entity type "event" and entityId of event must be submitted within the api call)
 *
 * If verification fails for one of the above reasons, it is stated in the reply (e.g. for displaying it on forms)
 *
 * If Verification is successful discountedAmount is given back, calculated on basis of the settings of the individual discount code (percentage or absolute discount).
 *
 */
class Verify extends AbstractAction {

  /**
   * @var string
   */
  protected string $discountCode = '';

  /**
   * @var string
   * @required
   */
  protected ?string $entityType = NULL;

  /**
   * @var int
   * @required
   */
  protected ?int $entityId = NULL;

  /**
   * Optional contact ID to verify discount code against
   *
   * @var int|null
   */
  protected ?int $contactId = NULL;

  /**
   * @var float
   * @required
   */
  protected ?float $amount = NULL;

  /**
   * Every action must define a _run function to perform the work and place results in the Result object.
   *
   * When using the set of Basic actions, they define _run for you and you just need to provide a getter/setter function.
   *
   * @param \Civi\Api4\Generic\Result $result
   *
   * @return void
   */
  public function _run(Result $result) {
    $discountCalculator = new \CRM_CiviDiscount_DiscountCalculator($this->entityType, $this->entityId, $this->contactId, $this->discountCode ?? NULL);

    if (empty($this->discountCode)) {
      $discounts = $discountCalculator->getAvailableDiscountsWithAutodiscount();
      if (empty($discounts)) {
        // Since we are checking for Autodiscounts we don't fail. We just return nothing.
        return;
      }
    }
    else {
      $discounts = $discountCalculator->getAvailableDiscountsWithCode();
      if (empty($discounts) || empty($discounts[$this->discountCode])) {
        // We specified a discountCode but have no valid discounts with that code.
        // Maybe because the code doesn't exist, maybe because the usage limits have been reached.
        $result[] = [
          'discountCode' => $this->discountCode,
          'verify' => 'failed',
          'reason' => \CRM_CiviDiscount_DiscountCalculator::getErrorMessage($this->discountCode, $this->contactId),
        ];
        return;
      }
      $discounts = [$discounts[$this->discountCode]];
    }

    // positive result
    foreach ($discounts as $discount) {
      [$discountedAmount, $discountDescription] = \CRM_CiviDiscount_DiscountCalculator::calculateDiscountAmount($discount, $this->amount, '', '', '');
      $result[] = [
        'verify' => 'success',
        'discount_id' => $discount['id'],
        // Compatibility
        'discountCode' => $discount['code'],
        'discount_code' => $discount['code'],
        'amount_to_discount' => $this->amount - $discountedAmount,
        // Compatibility
        'discountedAmount' => $discountedAmount,
        'total_after_discount' => $discountedAmount,
        'discount_description' => trim($discountDescription),
        'is_autodiscount' => !empty($discount['autodiscount']),
      ];
    }
  }

  /**
   * Declare ad-hoc field list for this action.
   *
   * Some actions return entirely different data to the entity's "regular" fields.
   *
   * This is a convenient alternative to adding special logic to our GetFields function to handle this action.
   *
   * @return array
   */
  public static function fields() {
    return [
      ['name' => 'discountCode'],
      ['name' => 'entityType'],
      ['name' => 'entityId', 'data_type' => 'Integer'],
      ['name' => 'amount', 'data_type' => 'Float'],
    ];
  }

}
