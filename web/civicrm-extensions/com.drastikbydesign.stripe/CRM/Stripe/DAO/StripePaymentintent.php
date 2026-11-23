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
 * @property string $stripe_intent_id
 * @property string $contribution_id
 * @property string $payment_processor_id
 * @property string $description
 * @property string $status
 * @property string $identifier
 * @property string $contact_id
 * @property string $created_date
 * @property string $flags
 * @property string $referrer
 * @property string $extra_data
 */
class CRM_Stripe_DAO_StripePaymentintent extends CRM_Stripe_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_stripe_paymentintent';

}
