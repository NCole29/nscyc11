<?php

use CRM_CiviDiscount_ExtensionUtil as E;

/**
 * @package CiviDiscount
 */
class CRM_CiviDiscount_BAO_Item extends CRM_CiviDiscount_DAO_Item {

  /**
   * Takes a bunch of params that are needed to match certain criteria and
   * retrieves the relevant objects. Typically the valid params are only
   * contact_id. We'll tweak this function to be more full featured over a period
   * of time. This is the inverse function of create. It also stores all the retrieved
   * values in the default array
   *
   * @param array $params (reference) an assoc array of name/value pairs
   * @param array $defaults (reference) an assoc array to hold the flattened values
   *
   * @return CRM_CiviDiscount_BAO_Item
   */
  public static function retrieve(&$params, &$defaults) {
    $item = new CRM_CiviDiscount_DAO_Item();
    $item->copyValues($params);
    if ($item->find(TRUE)) {
      CRM_Core_DAO::storeValues($item, $defaults);
      return $item;
    }
    return NULL;
  }

  /**
   * @var array
   */
  private static $discounts = [];

  /**
   * @var array
   */
  private static $queriedCodes = [];

  /**
   * @var bool
   */
  private static $queriedAll = FALSE;

  /**
   * Get discounts matching the given code, or all currently-valid discounts
   * if no code is given.
   *
   * Results are memoized in class-statics for the lifetime of the process.
   * To avoid one lookup silently reusing a *different* prior lookup's
   * result (the actual bug this caching is prone to), each distinct code
   * queried gets its own cache entry, and a full (no-code) fetch is
   * tracked separately - once that has run, every code is answerable from
   * the cache without hitting the DB again.
   *
   * Tests that need every class in the same PHPUnit process to see its own
   * fixtures (rather than whichever class happens to prime the cache
   * first) must call resetValidDiscountsCacheForTesting() before priming.
   *
   * @param string|null $code
   *
   * @return array
   */
  public static function getValidDiscounts($code = NULL) {
    $params = [];
    if ($code) {
      // Case-insensitive, matching the column's collation (and
      // getDiscountMatchingCode()'s existing comparison below).
      $normalizedCode = mb_strtolower(trim($code));
      if (self::$queriedAll || in_array($normalizedCode, self::$queriedCodes, TRUE)) {
        foreach (self::$discounts as $discountCode => $discount) {
          if (strcasecmp($code, $discountCode) === 0) {
            return [$discountCode => $discount];
          }
        }
        return [];
      }
      self::$queriedCodes[] = $normalizedCode;
      $codeCondition = 'code = %1';
      $params[1] = [$code, 'String'];
    }
    else {
      if (self::$queriedAll) {
        return self::$discounts;
      }
      self::$queriedAll = TRUE;
      $codeCondition = NULL;
    }

    $select = ["id",
      "code",
      "description",
      "frontend_description",
      "amount",
      "amount_type",
      "events",
      "pricesets",
      "memberships",
      "autodiscount",
      "expire_on",
      "active_on",
      "is_active",
      "discount_msg_enabled",
      "discount_msg",
      "count_use",
      "count_max",
      "count_user_max",
      "filters",
    ];

    $conditions = [
      "is_active = 1",
      "(active_on IS NULL OR active_on <= NOW())",
      "(expire_on IS NULL OR expire_on > NOW())",
      "(count_max = 0 OR count_max > count_use)",
    ];
    if ($codeCondition) {
      $conditions[] = $codeCondition;
    }

    $select = implode(', ', $select);
    $conditions = implode(' AND ', $conditions);
    $sql = "SELECT $select FROM cividiscount_item AS i WHERE $conditions";
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    while ($dao->fetch()) {
      $a = (array) $dao;
      // Not checking if empty $discounts as could be legitimately empty.
      self::$discounts[$a['code']] = self::buildDiscountFilters($a);
    }

    if ($code) {
      foreach (self::$discounts as $discountCode => $discount) {
        if (strcasecmp($code, $discountCode) === 0) {
          return [$discountCode => $discount];
        }
      }
      return [];
    }
    return self::$discounts;
  }

  /**
   * Clears getValidDiscounts()'s memoized cache.
   *
   * Test-only: lets each test class prime the cache with its own fixtures
   * regardless of what an earlier test class in the same PHPUnit process
   * already queried.
   */
  public static function resetValidDiscountsCacheForTesting(): void {
    self::$discounts = [];
    self::$queriedCodes = [];
    self::$queriedAll = FALSE;
  }

  /**
   * interpret filter values for return array
   * We are building one array out of 2 storage mechanisms - the json array in the filters field & the
   * hex(01) separated fields event, price_set & membership. Arguably these second type of fields should be dumped
   * & moved to filters as they are not easily searchable anyway
   *
   * We convert 'memberships' to membership_type_id as that is what the filter applies to
   *
   * We build an array that is effectively $entity => $params for api
   * Note that if the filter is 'any' (e.g any event) then we return $entity=> array() to achieve an
   * unfiltered api call
   * @param array $discount
   */
  public static function buildDiscountFilters($discount) {
    $filters = empty($discount['filters']) ? [] : json_decode($discount['filters'], TRUE);
    // Expand set-valued fields.
    $fields = [
      'events' => 'Event',
      'pricesets' => 'PriceSet',
      'memberships' => 'MembershipType',
    ];
    foreach ($fields as $field => $entity) {
      if (!isset($discount[$field]) || is_null($discount[$field])) {
        $items = [];
      }
      else {
        // Filter out empty strings from the array but not "0"
        $items = array_filter(explode(CRM_Core_DAO::VALUE_SEPARATOR, trim($discount[$field], CRM_Core_DAO::VALUE_SEPARATOR)), function($item) {
          return trim($item) !== '';
        });
        if (!empty($items)) {
          if (!isset($filters[$entity])) {
            $filters[$entity] = [];
          }
          //0 indicates 'any' so for 0 we construct an empty filter - otherwise we add a limit by id clause
          //note that this may be combined with stored filters e.g. 'event_type_id'
          if (in_array(0, $items)) {
            $filters[$entity]['id'] = ['IS NOT NULL' => TRUE];
          }
          else {
            $filters[$entity]['id'] = ['IN' => $items];
          }
        }
      }
      $discount[$field] = !empty($items) ? array_combine($items, $items) : [];
    }
    $discount['filters'] = $filters;
    $discount['autodiscount'] = empty($discount['autodiscount']) ? [] : json_decode($discount['autodiscount'], TRUE);
    return $discount;
  }

  /**
   * Does an available discount code exist for the entity?
   *
   * @param string $entity ('events' or 'memberships')
   * @return TRUE if an available discount code exists for that entity, FALSE if not
   */
  public static function validDiscountsByEntity($entity) {
    $sql = "
  SELECT id,
    events,
    memberships,
    filters
  FROM cividiscount_item
  WHERE is_active = 1
  AND (active_on IS NULL OR active_on <= NOW())
  AND (expire_on IS NULL OR expire_on > NOW())
  AND (count_max = 0 OR count_max > count_use)
";
    $dao = CRM_Core_DAO::executeQuery($sql, []);
    while ($dao->fetch()) {
      if ($dao->$entity) {
        return TRUE;
      }
      if ($entity === 'events' && $dao->filters) {
        $filters = json_decode($dao->filters, TRUE);
        if (isset($filters['Event'])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * @param int $id
   */
  public static function incrementUsage($id) {
    $sql = "UPDATE cividiscount_item SET count_use = count_use+1 WHERE id = {$id}";
    return CRM_Core_DAO::executeQuery($sql);
  }

  /**
   * @param int $id
   */
  public static function decrementUsage($id) {
    $sql = "UPDATE cividiscount_item SET count_use = count_use-1 WHERE id = {$id}";
    return CRM_Core_DAO::executeQuery($sql);
  }

  /**
   * Function to copy discount codes
   *
   * @param int $itemID
   *   ID of the discount code to be copied.
   * @param array $params
   * @param string $newCode
   *
   * @return bool
   */
  public static function copy($itemID, $params, $newCode) {
    $item = new CRM_CiviDiscount_DAO_Item();
    $item->id = $itemID;

    if ($item->find(TRUE)) {
      unset($item->id);
      $item->count_use = 0;
      $item->code = $newCode;
      if (isset($item->description) && $item->description != '') {
        $item->description = 'Copy of ' . $item->description;
      }

      CRM_Utils_Hook::pre('create', 'CiviDiscount', NULL, $params);
      $item->save();
      CRM_Utils_Hook::post('create', 'CiviDiscount', $item->id, $item);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Pseudoconstant callback for amount_types field
   * @return array
   */
  public static function amountTypes(): array {
    return [
      1 => E::ts('Percent'),
      2 => E::ts('Fixed Amount'),
    ];
  }

}
