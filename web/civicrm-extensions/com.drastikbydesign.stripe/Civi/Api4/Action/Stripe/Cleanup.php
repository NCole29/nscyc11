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

namespace Civi\Api4\Action\Stripe;

/**
 * This job performs various housekeeping actions related to the Stripe payment processor.
 * NOT domain-specific. On multidomain only run on one domain.
 */
class Cleanup extends \Civi\Api4\Generic\AbstractAction {

  /**
   * "canceled" is the status from Stripe, we used to record "cancelled" so we check for both.
   * "complete" is a terminal Checkout Session status (Civi\Stripe\Webhook\Events::doCheckoutSessionCompleted,
   * Civi\Stripe\CheckoutOption\StripeCheckoutOptionBase) - Checkout Session based tracking rows use the
   * Session's own status vocabulary (open/complete/expired), not the PaymentIntent's.
   */
  private const TERMINAL_STATUSES = ['succeeded', 'cancelled', 'canceled', 'failed', 'complete'];

  /**
   * Delete locally recorded paymentIntents in a terminal state, created before this. Specify 0 to disable.
   *
   * @var string|int
   */
  protected $deleteOld = '-3 month';

  /**
   * Cancel incomplete paymentIntents at Stripe, created before this. Specify 0 to disable.
   *
   * @var string|int
   */
  protected $cancelIncomplete = '-1 hour';

  /**
   * If specified, only this civicrm_stripe_paymentintent.id will be considered by cancelIncomplete.
   *
   * @var int|null
   */
  protected $stripePaymentintentId;

  /**
   * @param \Civi\Api4\Generic\Result $result
   *
   * @throws \CRM_Core_Exception
   */
  public function _run(\Civi\Api4\Generic\Result $result) {
    $results = [
      'deleted' => 0,
      'canceled' => 0,
      'succeeded' => 0,
    ];

    if ($this->deleteOld !== 0 && !empty($this->deleteOld)) {
      // Delete all locally recorded paymentIntents that are older than 3 months
      $oldPaymentintents = \Civi\Api4\StripePaymentintent::delete(FALSE)
        ->addWhere('status', 'IN', self::TERMINAL_STATUSES)
        ->addWhere('created_date', '<', $this->deleteOld)
        ->execute();
      $results['deleted'] = $oldPaymentintents->count();
    }

    if ($this->cancelIncomplete !== 0 && !empty($this->cancelIncomplete)) {
      // Cancel incomplete paymentIntents after 1 hour
      $incompletePaymentintents = \Civi\Api4\StripePaymentintent::get(FALSE)
        ->addWhere('status', 'NOT IN', self::TERMINAL_STATUSES)
        ->addWhere('created_date', '<', $this->cancelIncomplete)
        ->addWhere('stripe_intent_id', 'IS NOT EMPTY');
      if (!empty($this->stripePaymentintentId)) {
        $incompletePaymentintents->addWhere('id', '=', $this->stripePaymentintentId);
      }
      $incompletePaymentintents = $incompletePaymentintents->execute();

      $cancelledIDs = [];
      $succeededIDs = [];
      foreach ($incompletePaymentintents as $incompletePaymentintent) {
        try {
          /** @var \CRM_Core_Payment_Stripe $paymentProcessor */
          $paymentProcessor = \Civi\Payment\System::singleton()->getById($incompletePaymentintent['payment_processor_id']);
          $intent = $paymentProcessor->stripeClient->paymentIntents->retrieve($incompletePaymentintent['stripe_intent_id']);
          if ($intent->status === 'succeeded') {
            // The payment actually completed at Stripe despite our tracking row still looking
            // incomplete (eg. a missed/delayed webhook) - do not attempt to cancel it, and flag
            // loudly rather than letting the resulting error be treated as "already cancelled".
            \Civi::log('stripe')->error("Stripe.cleanup: paymentIntentID {$incompletePaymentintent['stripe_intent_id']} (civicrm_stripe_paymentintent.id {$incompletePaymentintent['id']}) has succeeded at Stripe but is still marked '{$incompletePaymentintent['status']}' locally - not cancelling. Check contribution_id {$incompletePaymentintent['contribution_id']} was completed correctly.");
            $succeededIDs[] = $incompletePaymentintent['id'];
            continue;
          }
          // Check intent was not already cancelled - this could happen eg. at Stripe before we process it
          if ($intent->status !== 'canceled') {
            $intent->cancel(['cancellation_reason' => 'abandoned']);
          }
          $cancelledIDs[] = $incompletePaymentintent['id'];
        }
        catch (\Exception $e) {
          if ($e instanceof \Stripe\Exception\InvalidRequestException) {
            // 404 resource_missing (paymentIntent not found at Stripe)
            // 400 bad_request (paymentIntent is already in canceled state?)
            if (in_array($e->getHttpStatus(), [400, 404])) {
              $cancelledIDs[] = $incompletePaymentintent['id'];
            }
          }
          \Civi::log('stripe')->error("Stripe.cleanup: Unable to cancel paymentIntentID: {$incompletePaymentintent['id']}: " . $e->getMessage());
        }
      }
      if (!empty($cancelledIDs)) {
        \Civi\Api4\StripePaymentintent::update(FALSE)
          ->addValue('status', 'canceled')
          ->addWhere('id', 'IN', $cancelledIDs)
          ->execute();
      }
      if (!empty($succeededIDs)) {
        // Correct the local record to match Stripe's actual status, so it isn't
        // re-flagged as incomplete on every subsequent run of this job.
        \Civi\Api4\StripePaymentintent::update(FALSE)
          ->addValue('status', 'succeeded')
          ->addWhere('id', 'IN', $succeededIDs)
          ->execute();
      }
      $results['canceled'] = count($cancelledIDs);
      $results['succeeded'] = count($succeededIDs);
    }

    $result[] = $results;
  }

}
