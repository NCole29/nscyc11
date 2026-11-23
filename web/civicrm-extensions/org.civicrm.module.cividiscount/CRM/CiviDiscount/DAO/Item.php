<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way. Property annotations may be added, but are not required.
 * @property string $id
 * @property string $code
 * @property string $description
 * @property string $amount
 * @property string $amount_type
 * @property int|string $count_max
 * @property int|string $count_use
 * @property string $events
 * @property string $pricesets
 * @property string $memberships
 * @property string $autodiscount
 * @property string $organization_id
 * @property string $active_on
 * @property string $expire_on
 * @property bool|string $is_active
 * @property bool|string $discount_msg_enabled
 * @property string $discount_msg
 * @property string $filters
 */
class CRM_CiviDiscount_DAO_Item extends CRM_CiviDiscount_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'cividiscount_item';

}
