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
use CRM_Stripe_ExtensionUtil as E;

class CRM_Stripe_BAO_StripePaymentintent extends CRM_Stripe_DAO_StripePaymentintent {

  /**
   * Create a new StripePaymentintent based on array-data
   *
   * @param array $params key-value pairs
   *
   * @return \CRM_Core_DAO
   * @deprecated
   */
  public static function create($params) {
    CRM_Core_Error::deprecatedFunctionWarning('writeRecord');
    return self::writeRecord($params);
  }

  /**
   * Create or update a record from supplied params.
   *
   * If 'id' is supplied, an existing record will be updated
   * Otherwise a new record will be created.
   *
   * @param array $record
   *
   * @return static
   * @throws \CRM_Core_Exception
   */
  public static function writeRecord(array $record): CRM_Core_DAO {
    $existingRecord = NULL;
    if (!empty($record['stripe_intent_id'])) {
      // This checks if we already recorded the intent_id. In that case we need to update it.
      $existingRecord = StripePaymentintent::get(FALSE)
        ->addSelect('id', 'flags', 'referrer')
        ->addWhere('stripe_intent_id', '=', $record['stripe_intent_id'])
        ->execute()
        ->first();
    }
    if (empty($existingRecord) && !empty($record['identifier'])) {
      // We may not know the stripe_intent_id yet (eg. a Checkout Session in
      // subscription mode doesn't have one until the first invoice is paid),
      // so fall back to matching on the caller-supplied identifier
      // (eg. the Checkout Session id) to update rather than duplicate.
      $existingRecord = StripePaymentintent::get(FALSE)
        ->addSelect('id', 'flags', 'referrer')
        ->addWhere('identifier', '=', $record['identifier'])
        ->execute()
        ->first();
    }
    if (!empty($existingRecord['id'])) {
      $record['id'] = $existingRecord['id'];
    }

    $flags = empty($existingRecord['flags']) ? [] : unserialize($existingRecord['flags']);
    if (!empty($record['flags']) && is_array($record['flags'])) {
      foreach ($record['flags'] as $flag) {
        if (!in_array($flag, $flags)) {
          $flags[] = 'NC';
        }
      }
      unset($record['flags']);
    }
    $record['flags'] = serialize($flags);

    // Use existing referrer if we have one.
    $record['referrer'] = empty($existingRecord['referrer']) ? '' : $existingRecord['referrer'];
    if (!empty($_SERVER['HTTP_REFERER']) && empty($existingRecord['referrer'])) {
      // Otherwise, use the webserver referrer
      $record['referrer'] = $_SERVER['HTTP_REFERER'];
    }
    // The referrer is use-supplied, so could be anything. Ensure it doesn't exceed the field size.
    $record['referrer'] = substr($record['referrer'], 0, 1024);

    return parent::writeRecord($record);
  }

}
