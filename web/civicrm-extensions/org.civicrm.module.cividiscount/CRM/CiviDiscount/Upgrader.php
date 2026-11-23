<?php

/**
 * Collection of upgrade steps
 */
class CRM_CiviDiscount_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_2201() {
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('cividiscount_item', 'discount_msg_enabled')) {
      $this->ctx->log->info('Skipped cividiscount update 2201.  Column discount_msg_enabled already present on cividiscount_item table.');
    }
    else {
      $this->ctx->log->info('Applying cividiscount update 2201.  Adding discount_msg_enabled to the cividiscount_item table.');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_item ADD COLUMN discount_msg_enabled TINYINT(1) DEFAULT 0 AFTER is_active');
    }
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('cividiscount_item', 'discount_msg')) {
      $this->ctx->log->info('Skipped cividiscount update 2201.  Column discount_msg already present on cividiscount_item table.');
    }
    else {
      $this->ctx->log->info('Applying cividiscount update 2201.  Adding discount_msg to the cividiscount_item table.');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_item ADD COLUMN discount_msg VARCHAR(255) AFTER discount_msg_enabled');
    }
    return TRUE;
  }

  public function upgrade_2202() {
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('cividiscount_item', 'filters')) {
      $this->ctx->log->info('Skipped cividiscount update 2202.  Column filters already present on cividiscount_item table.');
    }
    else {
      $this->ctx->log->info('Applying cividiscount update 2202.  Adding filters to the cividiscount_item table.');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_item ADD COLUMN filters VARCHAR(255)');
    }
    return TRUE;
  }

  public function upgrade_2203() {
    $sql = "SELECT id, autodiscount FROM cividiscount_item WHERE autodiscount IS NOT NULL";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $discount = json_encode(['membership' => ['membership_type_id' => ['IN' => explode(CRM_Core_DAO::VALUE_SEPARATOR, trim($dao->autodiscount, CRM_Core_DAO::VALUE_SEPARATOR))]]]);
      CRM_Core_DAO::executeQuery("UPDATE cividiscount_item SET autodiscount = %1 WHERE id = %2", [
        1 => [$discount, 'String'],
        2 => [$dao->id, 'Integer'],
      ]);
    }
    return TRUE;
  }

  public function upgrade_2204() {
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('cividiscount_track', 'track')) {
      $this->ctx->log->info('Applying cividiscount update 2204. Dropping column track from the cividiscount_track table.');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_track DROP COLUMN track');
    }
    else {
      $this->ctx->log->info('Skipped cividiscount update 2204. Column track not present on cividiscount_track table.');
    }
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('cividiscount_track', 'event_id')) {
      $this->ctx->log->info('Applying cividiscount update 2204. Dropping column event_id from the cividiscount_track table.');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_track DROP FOREIGN KEY FK_cividiscount_track_event_id');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_track DROP COLUMN event_id');
    }
    else {
      $this->ctx->log->info('Skipped cividiscount update 2204. Column event_id not present on cividiscount_track table.');
    }
    return TRUE;
  }

  public function upgrade_2205() {
    CRM_Core_DAO::executeQuery("ALTER TABLE `cividiscount_item` MODIFY `count_max` int NOT NULL DEFAULT 0 COMMENT 'Max number of times this code can be used'");
    self::addColumn('cividiscount_item', 'count_user_max', "int NOT NULL DEFAULT 0 COMMENT 'Max number of times a user can use this code' AFTER `count_max`");
    return TRUE;
  }

  public function upgrade_2206() {
    $this->ctx->log->info('Add frontend_description to cividiscount_item table.');
    self::addColumn('cividiscount_item', 'frontend_description', "varchar(255) NOT NULL COMMENT 'Public Description'");
    return TRUE;
  }

  public function upgrade_2207() {
    $this->ctx->log->info('Update filters for cividiscount_item table.');
    self::updateFilters();
    return TRUE;
  }

  public function upgrade_2208() {
    // Neither `code` nor `is_active` had an index, so every lookup by code
    // (CRM_CiviDiscount_BAO_Item::getValidDiscounts()) or eligibility scan
    // (getValidDiscounts()/validDiscountsByEntity()) was a full table scan.
    if (CRM_Core_BAO_SchemaHandler::checkIfIndexExists('cividiscount_item', 'INDEX_code')) {
      $this->ctx->log->info('Skipped cividiscount update 2208. Index INDEX_code already present on cividiscount_item table.');
    }
    else {
      $this->ctx->log->info('Applying cividiscount update 2208. Adding INDEX_code to the cividiscount_item table.');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_item ADD INDEX INDEX_code (code)');
    }
    if (CRM_Core_BAO_SchemaHandler::checkIfIndexExists('cividiscount_item', 'INDEX_is_active')) {
      $this->ctx->log->info('Skipped cividiscount update 2208. Index INDEX_is_active already present on cividiscount_item table.');
    }
    else {
      $this->ctx->log->info('Applying cividiscount update 2208. Adding INDEX_is_active to the cividiscount_item table.');
      CRM_Core_DAO::executeQuery('ALTER TABLE cividiscount_item ADD INDEX INDEX_is_active (is_active)');
    }
    return TRUE;
  }

  private static function updateFilters() {
    $map = [
      'event' => 'Event',
      'price_set' => 'PriceSet',
      'membership_type' => 'MembershipType',
    ];
    $discountCodes = \Civi\Api4\DiscountCode::get(FALSE)
      ->addSelect('id', 'filters')
      ->addWhere('filters', 'IS NOT EMPTY')
      ->execute();
    foreach ($discountCodes as $discountCode) {
      foreach ($map as $before => $after) {
        if (array_key_exists($before, $discountCode['filters'])) {
          $discountCode['filters'][$after] = $discountCode['filters'][$before];
          unset($discountCode['filters'][$before]);
          $discountCodesToUpdate[] = $discountCode;
        }
      }
    }
    if (!empty($discountCodesToUpdate)) {
      \Civi\Api4\DiscountCode::save(FALSE)
        ->setRecords($discountCodesToUpdate)
        ->execute();
    }
  }

}
