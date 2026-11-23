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

namespace Civi\Api4\Action\StripeCharge;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * @inheritDoc
 */
class GetBalanceTransactionDetails extends AbstractAction {

  /**
   * Stripe Charge ID
   *
   * @var string
   */
  protected string $chargeID = '';

  /**
   * The CiviCRM Payment Processor ID
   *
   * @var int
   */
  protected int $paymentProcessorID;

  /**
   * @param \Civi\Api4\Generic\Result $result
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function _run(Result $result) {
    if (empty($this->chargeID)) {
      throw new \CRM_Core_Exception('Missing chargeID');
    }
    if (empty($this->paymentProcessorID)) {
      throw new \CRM_Core_Exception('Missing paymentProcessorID');
    }

    $stripeApi = new \Civi\Stripe\Api(\Civi\Payment\System::singleton()->getById($this->paymentProcessorID));

    $charge = $stripeApi->getPaymentProcessor()->stripeClient->charges->retrieve($this->chargeID);

    $stripeEvent = new \Stripe\Event();
    $stripeEvent->object = $charge;
    $stripeApi->setData($stripeEvent);
    $balanceTransactionDetails = $stripeApi->getDetailsFromBalanceTransactionByChargeObject($stripeEvent->object);

    $result->exchangeArray($balanceTransactionDetails);
  }

}
