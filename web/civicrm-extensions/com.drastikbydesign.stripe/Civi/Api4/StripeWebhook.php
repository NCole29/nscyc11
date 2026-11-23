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
namespace Civi\Api4;

/**
 * CiviCRM StripeWebhook API
 *
 * Used to get, create and update Stripe Webhooks
 *
 * @searchable none
 * @package Civi\Api4
 */
class StripeWebhook extends Generic\AbstractEntity {

  /**
   * @param bool $checkPermissions
   * @return Generic\BasicGetFieldsAction
   */
  public static function getFields($checkPermissions = TRUE) {
    return (new Generic\BasicGetFieldsAction(static::getEntityName(), __FUNCTION__, function() {
      return [];
    }))->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return Action\StripeWebhook\GetFromStripe
   */
  public static function getFromStripe($checkPermissions = TRUE) {
    return (new Action\StripeWebhook\GetFromStripe(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return Action\StripeWebhook\Create
   */
  public static function create($checkPermissions = TRUE) {
    return (new Action\StripeWebhook\Create(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

  /**
   * @param bool $checkPermissions
   * @return Action\StripeWebhook\Update
   */
  public static function update($checkPermissions = TRUE) {
    return (new Action\StripeWebhook\Update(__CLASS__, __FUNCTION__))
      ->setCheckPermissions($checkPermissions);
  }

}
