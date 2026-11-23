<?php

use Civi\Api4\DiscountTrack;
use Civi\Api4\PriceFieldValue;
use CRM_CiviDiscount_ExtensionUtil as E;

/**
 * @package CiviDiscount
 */
class CRM_CiviDiscount_DiscountCalculator {

  /**
   * @var string
   */
  protected string $entity;

  /**
   * @var int|null
   */
  protected ?int $entity_id;

  /**
   * @var array
   */
  protected array $discounts = [];

  /**
   * @var int|null
   */
  protected ?int $contact_id;

  /**
   * @var string|null
   */
  protected ?string $code;

  /**
   * Discounts which apply directly to entities
   *
   * @var array
   */
  protected array $entityDiscounts;

  /**
   * Discounts which apply to priceSets
   *
   * @var array
   */
  protected array $priceSetDiscounts;

  /**
   * Applicable automatic discounts.
   *
   * @var array
   */
  public array $autoDiscounts = [];

  /**
   * Applicable discounts with code.
   *
   * @var array
   */
  public array $codeDiscounts = [];

  /**
   * @var ?int
   */
  private ?int $price_set_id;

  /**
   * Constructor.
   *
   * @param string $entity
   * @param int|null $entity_id
   * @param int|null $contact_id
   * @param string|null $code
   * @param ?int $priceSetID
   */
  public function __construct(string $entity, ?int $entity_id, ?int $contact_id, ?string $code, ?int $priceSetID = NULL) {
    $code = ($code !== NULL) ? trim($code) : NULL;
    if (!empty($code)) {
      $this->discounts = CRM_CiviDiscount_BAO_Item::getValidDiscounts($code);
    }
    elseif (!empty($contact_id)) {
      $this->discounts = CRM_CiviDiscount_BAO_Item::getValidDiscounts();
    }
    else {
      $this->discounts = [];
    }
    $this->entity = $entity;
    $this->contact_id = $contact_id;
    $this->entity_id = $entity_id;
    $this->code = ($code) ? trim($code) : $code;
    $this->price_set_id = $priceSetID;
  }

  /**
   * Get discounts that could apply in this instance.
   *
   * @return array
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function getDiscounts(): array {
    $this->filterDiscountsByUserMax();
    $this->filterDiscountsByEntity();
    return $this->entityDiscounts + $this->priceSetDiscounts;
  }

  /**
   * Get a list of all available discounts with code
   *   based on current contact, current entity etc.
   *
   * @return array
   */
  public function getAvailableDiscountsWithCode(): array {
    $this->codeDiscounts = $this->getDiscounts();
    return $this->filterDiscountsWithCode();
  }

  /**
   *  Get a list of all available autodiscounts
   *    based on current contact, current entity etc.
   *
   * @return array
   */
  public function getAvailableDiscountsWithAutodiscount(): array {
    $this->autoDiscounts = $this->getDiscounts();
    return $this->filterDiscountByAutodiscount();
  }

  /**
   * Filter $discounts by entity/entityID.
   *
   * @return void
   */
  protected function filterDiscountsByEntity(): void {
    $this->entityDiscounts = $this->priceSetDiscounts = [];
    foreach ($this->discounts as $discount_id => $discount) {
      // WARNING! The previous attempted to improve performance in deciding when
      // the autoDiscount field should be displayed resulted in breakage.
      // See https://github.com/dlobo/org.civicrm.module.cividiscount/issues/145 before
      // attempting.
      if ($this->checkDiscountsByEntity($discount, $this->entity, $this->entity_id, 'filters')) {
        $this->entityDiscounts[$discount_id] = $discount;
      }
      if ($this->checkDiscountsByEntity($discount, 'PriceSet', $this->price_set_id, 'filters')) {
        $this->priceSetDiscounts[$discount_id] = $discount;
      }
    }
  }

  /**
   * Filter $discounts by count_user_max
   * Removes any discounts that have exceeded usage limit from $this->discounts
   *
   * @return void
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function filterDiscountsByUserMax(): void {
    if (!$this->contact_id) {
      return;
    }
    $discountsToLimit = array_filter($this->discounts, fn($discount) => !empty($discount['count_user_max']));
    if ($discountsToLimit) {
      $uses = DiscountTrack::get(FALSE)
        ->addSelect('item_id.code', 'COUNT(id) AS uses')
        ->addWhere('item_id.code', 'IN', array_keys($discountsToLimit))
        ->addWhere('contact_id', '=', $this->contact_id)
        ->addGroupBy('item_id')
        ->execute()
        ->column('uses', 'item_id.code');
      foreach ($uses as $code => $use) {
        if ($use >= $discountsToLimit[$code]['count_user_max']) {
          unset($this->discounts[$code]);
        }
      }
    }
  }

  /**
   * Filter discounts by auto-discount criteria.
   *
   * If any one of the criteria is not met for this contact then the discount
   * does not apply
   *
   * We can assume that the no-contact id situation is dealt with in that
   * our scenarios are
   * - no contact id but code - in which case we will already be filtered down to code
   * - no contact id, no code - ie. anonymous mode so we don't need to filter by contact
   */
  protected function filterAutoDiscountsByContact(): void {
    if (empty($this->contact_id)) {
      return;
    }
    foreach ($this->autoDiscounts as $discount_id => $discount) {
      foreach (array_keys($discount['autodiscount']) as $entity) {
        $additionalParams = ['contact_id' => $this->contact_id];
        $id = ($entity === 'Contact') ? $this->contact_id : NULL;
        if (!$this->checkDiscountsByEntity($discount, $entity, $id, 'autodiscount', $additionalParams)) {
          unset($this->autoDiscounts[$discount_id]);
        }
      }
    }
  }

  /**
   * Check if the discount-code input field should be shown for an entity.
   *
   * Two-stage SQL strategy avoids materialising the full discount set:
   *   1. Fast path — a single LIMIT-1 query that checks the VALUE_SEPARATOR-
   *      encoded `events` / `memberships` / `pricesets` / `event_type_ids`
   *      columns directly. Catches the common case (specific or "any" entity
   *      ID) without any PHP filtering.
   *   2. Slow path — only triggered when the fast path finds nothing. SQL
   *      returns just the IDs of discounts whose specific `pricesets` entries
   *      need API-level evaluation; PHP/API checks then run against that
   *      narrow candidate set instead of the full table.
   *   3. When the slow path also yields zero candidates, we return FALSE
   *      without ever loading a discount row into PHP.
   *
   * @param string $entity
   * @param int|null $entityId
   * @param int|null $priceSetID
   * @param int|null $contactId
   *
   * @return bool
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function isShowDiscountCodeField(string $entity, ?int $entityId, ?int $priceSetID, ?int $contactId = NULL): bool {
    $sep = CRM_Core_DAO::VALUE_SEPARATOR;
    $entityColumn = match ($entity) {
      'Event' => 'events',
      'MembershipType' => 'memberships',
      default => NULL,
    };

    // Valid, code-only and (if contact known) not-exceeded-per-user.
    $baseWhere = "is_active = 1
      AND (active_on IS NULL OR active_on <= NOW())
      AND (expire_on IS NULL OR expire_on > NOW())
      AND (count_max = 0 OR count_max > count_use)
      AND (autodiscount IS NULL OR autodiscount IN ('', '[]', '{}'))";

    $params = [];
    if ($contactId) {
      $params[1] = [$contactId, 'Integer'];
      $baseWhere .= "
        AND (
          count_user_max = 0
          OR count_user_max > COALESCE((
            SELECT COUNT(*) FROM cividiscount_track t
            WHERE t.item_id = cividiscount_item.id AND t.contact_id = %1
          ), 0)
        )";
    }

    // Stage 1 — direct match against the separator-encoded columns.
    $directClauses = [];
    if ($entityColumn && $entityId) {
      $params[2] = ["%{$sep}{$entityId}{$sep}%", 'String'];
      $directClauses[] = "{$entityColumn} LIKE %2";
      $params[3] = ["%{$sep}0{$sep}%", 'String'];
      $directClauses[] = "{$entityColumn} LIKE %3";
      if ($entity === 'Event') {
        $eventTypeId = (int) CRM_Core_DAO::singleValueQuery(
          'SELECT event_type_id FROM civicrm_event WHERE id = %1',
          [1 => [$entityId, 'Integer']]
        );
        if ($eventTypeId) {
          $params[5] = ["%{$sep}{$eventTypeId}{$sep}%", 'String'];
          $directClauses[] = "filters LIKE %5";
        }
      }
    }
    if ($priceSetID) {
      // "Any priceset" marker. Specific PriceFieldValue IDs need a join, so
      // they fall through to stage 2 below.
      $params[4] = ["%{$sep}0{$sep}%", 'String'];
      $directClauses[] = "pricesets LIKE %4";
    }
    if (!empty($directClauses)) {
      $directSql = "SELECT 1 FROM cividiscount_item
        WHERE {$baseWhere}
        AND (" . implode(' OR ', $directClauses) . ")
        LIMIT 1";
      if (CRM_Core_DAO::singleValueQuery($directSql, $params)) {
        return TRUE;
      }
    }

    // Stage 2 — candidates whose specific `pricesets` entries still need
    // PriceFieldValue-level verification.
    if (!$priceSetID) {
      return FALSE;
    }
    $candidateSql = "SELECT id FROM cividiscount_item
      WHERE {$baseWhere}
      AND ((pricesets IS NOT NULL AND pricesets != '')
      OR (filters IS NOT NULL AND filters != ''))";
    $candidateIds = [];
    $dao = CRM_Core_DAO::executeQuery($candidateSql, $params);
    while ($dao->fetch()) {
      $candidateIds[] = (int) $dao->id;
    }
    if (empty($candidateIds)) {
      return FALSE;
    }

    // Stage 3 — run the existing entity/priceset evaluation on the narrow
    // candidate set. getValidDiscounts() is statically cached so this stays
    // a single table read for the whole request.
    $calculator = new self($entity, $entityId, $contactId, NULL, $priceSetID);
    $allValid = CRM_CiviDiscount_BAO_Item::getValidDiscounts();
    $calculator->discounts = array_filter(
      $allValid,
      fn($d) => in_array((int) $d['id'], $candidateIds, TRUE)
    );
    return !empty($calculator->getAvailableDiscountsWithCode());
  }

  /**
   * Check if discount is applicable.
   *
   * We check the 'filters' to see if
   * 1) there are any filters for this entity type - no filter means NO
   * 2) there is an empty filter for this entity type - means 'any'
   * 3) the only filter is on id (in which case we will do a direct comparison
   * 4) there is an api filter
   *
   * @param array $discount
   * @param string $entity
   * @param int|null $entityID entity id
   * @param string $type 'filters' or autodiscount
   * @param array $additionalFilter e.g array('contact_id' => x) when looking at memberships
   *
   * @return bool
   */
  protected function checkDiscountsByEntity(array $discount, string $entity, ?int $entityID, string $type, array $additionalFilter = []): bool {
    try {
      if (!isset($discount[$type][$entity])) {
        return FALSE;
      }
      if (empty($discount[$type][$entity])) {
        return TRUE;
      }
      $entityDiscountDetail = $discount[$type][$entity];
      if (count($entityDiscountDetail) === 1 && !empty($entityDiscountDetail['id'])) {
        if (array_key_first($entityDiscountDetail['id']) === 'IS NOT NULL') {
          return TRUE;
        }
        // If this discount is only limited by specific entity (say, a specific
        // event and not an event type), we have the IDs already and don't need
        // to make an API call. Store the IDs in a structure like they would
        // have as the result of an API call.
        $ids = $discount[$type][$entity]['id']['IN'];
      }
      else {
        $params = $discount[$type][$entity] + array_merge([
          'options' => ['limit' => 0],
          'return' => 'id',
        ], $additionalFilter);
        $ids = array_keys(civicrm_api3($entity, 'get', $params)['values']);
      }
      if ($entity === 'PriceSet') {
        return (bool) PriceFieldValue::get(FALSE)
          ->addWhere('id', 'IN', $ids)
          ->addWhere('price_field_id.price_set_id', '=', $this->price_set_id)
          ->execute()->count();
      }

      if ($entityID) {
        return in_array($entityID, $ids, FALSE);
      }
      return !empty($ids);
    }
    catch (CRM_Core_Exception $e) {
      return FALSE;
    }
  }

  /**
   * Filter discounts by ones which are code only (ie. no autodiscount)
   *
   * @return array
   */
  protected function filterDiscountsWithCode(): array {
    foreach ($this->codeDiscounts as $id => $discount) {
      if (!empty($discount['autodiscount'])) {
        unset($this->codeDiscounts[$id]);
      }
    }
    return $this->codeDiscounts;
  }

  /**
   * Unset any discounts that are not autodiscount.
   *
   * @return array
   */
  protected function filterDiscountByAutodiscount(): array {
    foreach ($this->autoDiscounts as $id => $discount) {
      if (empty($discount['autodiscount'])) {
        unset($this->autoDiscounts[$id]);
      }
    }
    $this->filterAutoDiscountsByContact();
    return $this->autoDiscounts;
  }

  /**
   * Given an invalid code, returns message to tell the user why the code cannot be used.
   *
   * @param string $code
   * @param int|null $contactId
   *
   * @return string
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function getErrorMessage(string $code, ?int $contactId): string {
    $countMax = CRM_Core_DAO::getFieldValue('CRM_CiviDiscount_DAO_Item', $code, 'count_max', 'code');
    if ($countMax) {
      $countUse = CRM_Core_DAO::getFieldValue('CRM_CiviDiscount_DAO_Item', $code, 'count_use', 'code');
      if ($countUse >= $countMax) {
        return E::ts('There are not enough uses remaining for this code.');
      }
    }
    $userMax = CRM_Core_DAO::getFieldValue('CRM_CiviDiscount_DAO_Item', $code, 'count_user_max', 'code');
    if ($userMax && $contactId) {
      $usageCount = DiscountTrack::get(FALSE)
        ->addWhere('item_id.code', '=', $code)
        ->addWhere('contact_id', '=', $contactId)
        ->selectRowCount()
        ->execute()
        ->count();
      if ($usageCount >= $userMax) {
        return E::ts('You have already used this code.', [
          'count' => $usageCount,
          'plural' => 'You have already used this code %count times.',
        ]);
      }
    }
    return E::ts('The discount code you entered is invalid.');
  }

  /**
   * Calculate either a monetary or percentage discount.
   *
   * @param array $discount
   * @param float $amount
   * @param string $label
   * @param string $currency
   * @param string $title
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  public static function calculateDiscountAmount(array $discount, float $amount, string $label = '', string $currency = '', string $title = 'Includes applied discount') {
    if (empty($currency)) {
      $currency = \Civi::settings()->get('defaultCurrency');
    }

    // This allows default title to be translated.
    $title = ($title === 'Includes applied discount') ? E::ts('Includes applied discount') : $title;
    if (!empty($title)) {
      $title = "$title: ";
    }
    $description = $discount['frontend_description'] ?? $discount['description'];

    if ($discount['amount_type'] == '2') {
      $newAmount = CRM_Utils_Rule::cleanMoney($amount) - CRM_Utils_Rule::cleanMoney($discount['amount']);
      $fmt_discount = CRM_Utils_Money::format($discount['amount'], $currency);
      $newLabel = $label . " ({$title}{$fmt_discount} {$description})";
    }
    else {
      $newAmount = $amount - ($amount * ($discount['amount'] / 100));
      $newLabel = $label . " ({$title}{$discount['amount']}% {$description})";
    }

    $newAmount = round($newAmount, 2);
    // Return a formatted string for zero amount.
    if ($newAmount <= 0) {
      $newAmount = '0.00';
    }

    return [$newAmount, $newLabel];
  }

}
