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
 * @property string $item_id
 * @property string $contact_id
 * @property string $used_date
 * @property string $contribution_id
 * @property string $entity_table
 * @property string $entity_id
 */
class CRM_CiviDiscount_DAO_Track extends CRM_CiviDiscount_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'cividiscount_track';

}
