<?php
namespace Civi\Api4;

/**
 * A collection of Stripe extension maintenance/housekeeping actions.
 *
 * Provided by the Stripe extension
 *
 * @searchable none
 * @package Civi\Api4
 */
class Stripe extends Generic\AbstractEntity {

  public static function permissions() {
    $permissions = parent::permissions();
    $permissions['cleanup'] = ['administer CiviCRM'];
    return $permissions;
  }

  /**
   * @param bool $checkPermissions
   * @return Generic\BasicGetFieldsAction
   */
  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction(__CLASS__, __FUNCTION__, function() {
      return [];
    }))->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return Action\Stripe\Cleanup
   */
  public static function cleanup($checkPermissions = TRUE) {
    return (new Action\Stripe\Cleanup(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
