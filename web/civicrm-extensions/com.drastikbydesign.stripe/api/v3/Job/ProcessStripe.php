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

/**
 * This job performs various housekeeping actions related to the Stripe payment processor
 * NOT domain-specific. On multidomain only run on one domain.
 *
 * @param array $params
 *
 * @return array
 *   API result array.
 * @throws CRM_Core_Exception
 * @deprecated Use API4 Stripe.cleanup
 */
function civicrm_api3_job_process_stripe($params) {
  $result = \Civi\Api4\Stripe::cleanup(FALSE)
    ->setDeleteOld($params['delete_old'])
    ->setCancelIncomplete($params['cancel_incomplete'])
    ->setStripePaymentintentId($params['stripe_paymentintent_id'] ?? NULL)
    ->execute()
    ->first();

  return civicrm_api3_create_success($result, $params);
}

/**
 * Action Payment.
 *
 * @param array $params
 */
function _civicrm_api3_job_process_stripe_spec(&$params) {
  $params['delete_old']['api.default'] = '-3 month';
  $params['delete_old']['title'] = 'Delete old records after (default: -3 month)';
  $params['delete_old']['description'] = 'Delete old records from database. Specify 0 to disable. Default is "-3 month"';
  $params['cancel_incomplete']['api.default'] = '-1 hour';
  $params['cancel_incomplete']['title'] = 'Cancel incomplete records after (default: -1hour)';
  $params['cancel_incomplete']['description'] = 'Cancel incomplete paymentIntents in your stripe account. Specify 0 to disable. Default is "-1hour"';
  $params['stripe_paymentintent_id']['title'] = 'ID of record from civicrm_stripe_paymentintent table';
  $params['stripe_paymentintent_id']['description'] = 'If specified, only this record will be processed';
  $params['stripe_paymentintent_id']['type'] = CRM_Utils_Type::T_INT;

}
