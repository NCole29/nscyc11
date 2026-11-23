<?php

use CRM_CiviDiscount_ExtensionUtil as E;

/**
 * @package CiviDiscount
 */
class CRM_CiviDiscount_BAO_Track extends CRM_CiviDiscount_DAO_Track {

  /**
   * Add the membership log record.
   *
   * @param array $params
   *   Values to use in create.
   *
   * @return CRM_CiviDiscount_DAO_Track
   */
  public static function create($params) {
    $dao = self::writeRecord($params);
    if (empty($params['id'])) {
      CRM_CiviDiscount_BAO_Item::incrementUsage($dao->item_id);
    }
    return $dao;
  }

  /**
   * Whitelist of possible values for the entity_table field
   *
   * @return array
   */
  public static function entityTables(): array {
    return [
      'civicrm_contribution' => E::ts('Contribution'),
      'civicrm_participant' => E::ts('Participant'),
      'civicrm_membership' => E::ts('Membership'),
    ];
  }

}
