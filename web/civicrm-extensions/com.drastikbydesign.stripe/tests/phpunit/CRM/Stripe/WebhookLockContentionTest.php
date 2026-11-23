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

use Civi\Api4\PaymentprocessorWebhook;

require_once 'TestBase.php';

/**
 * @file
 *
 * Webhook handlers guard their read-check-then-write sections with a
 * per-contribution MySQL lock. These tests cover what happens when that lock
 * cannot be obtained: the handler must leave the event for a later run rather
 * than proceed unsynchronised.
 *
 * Note that lock contention cannot be simulated by acquiring the same lock
 * twice from the test process, because GET_LOCK() is re-entrant within a
 * single MySQL session. holdContributionLock() therefore takes the lock on a
 * second connection.
 *
 * @group headless
 */
class CRM_Stripe_WebhookLockContentionTest extends CRM_Stripe_TestBase {

  /**
   * Second MySQL connection used to hold a contended lock, closed in tearDown.
   *
   * @var \mysqli|null
   */
  private ?\mysqli $lockHolder = NULL;

  public function tearDown(): void {
    if ($this->lockHolder !== NULL) {
      // Closing the connection releases anything it holds, but be explicit so
      // a leaked lock cannot outlive the test if the handle is reused.
      $this->lockHolder->query('SELECT RELEASE_ALL_LOCKS()');
      $this->lockHolder->close();
      $this->lockHolder = NULL;
    }
    parent::tearDown();
  }

  /**
   * A refund that cannot obtain the contribution lock must be left for a
   * later run, and must not record the refund in the meantime.
   */
  public function testRefundWithContendedLockIsLeftForRetry(): void {
    $doPaymentResult = $this->mockOneOffPaymentSetup();
    if ($doPaymentResult['payment_status'] === 'Completed') {
      civicrm_api3('Payment', 'create', [
        'trxn_id' => $doPaymentResult['trxn_id'],
        'total_amount' => $this->total,
        'fee_amount' => $doPaymentResult['fee_amount'],
        'order_reference' => $doPaymentResult['order_reference'],
        'contribution_id' => $this->contributionID,
      ]);
    }

    $this->holdContributionLock($this->contributionID);

    $result = $this->simulateEventReturningResult([
      'type'             => 'charge.refunded',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'              => 'ch_mock',
          'object'          => 'charge',
          'customer'        => 'cus_mock',
          'charge'          => 'ch_mock',
          'created'         => time(),
          'amount_refunded' => $this->total * 100,
          'status'          => 'succeeded',
          'captured'        => TRUE,
        ],
      ],
    ]);

    $this->assertTrue($result->retry, 'Contended lock should flag the event for retry');
    $this->assertFalse($result->ok, 'A retryable event has not been processed, so is not ok');

    // The refund must not have been recorded while the lock was held elsewhere.
    $this->checkContrib(['contribution_status_id' => 'Completed']);
  }

  /**
   * checkout.session.completed writes trxn_id unconditionally, and it is not
   * the only writer of that contribution: doChargeSucceeded records the same
   * one from a different webhook, and Stripe gives no ordering guarantee
   * between them.
   *
   * Observed before this was locked, on a one-off signup under three
   * concurrent queue drainers: doChargeSucceeded had written
   * "pi_x,ch_y" and a later checkout.session.completed overwrote it with
   * "pi_x", losing the charge id. The same run left the contribution Pending
   * after it had been completed and its receipt sent.
   *
   * The assertion that discriminates is on trxn_id, not on the retry flag:
   * an unlocked handler flags nothing and writes anyway.
   */
  public function testCheckoutSessionCompletedWithContendedLockIsLeftForRetry(): void {
    $this->mockOneOffPaymentSetup();

    $contribution = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $this->contributionID)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute()
      ->single();
    $trxnIDBefore = (string) ($contribution['trxn_id'] ?? '');

    $this->holdContributionLock($this->contributionID);

    $result = $this->simulateEventReturningResult([
      'type'             => 'checkout.session.completed',
      'id'               => 'evt_mock',
      'object'           => 'event',
      'livemode'         => FALSE,
      'pending_webhooks' => 0,
      'request'          => ['id' => NULL],
      'data'             => [
        'object' => [
          'id'                  => 'cs_mock',
          'object'              => 'checkout.session',
          'customer'            => 'cus_mock',
          'payment_intent'      => 'pi_mock',
          'client_reference_id' => $contribution['invoice_id'],
        ],
      ],
    ]);

    // Asserted before the flags deliberately. An unlocked handler fails the
    // retry assertion too, but it fails it on a symptom; this is the damage
    // the lock exists to prevent, so it should be what a regression reports.
    $after = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $this->contributionID)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute()
      ->single();
    $this->assertEquals($trxnIDBefore, (string) ($after['trxn_id'] ?? ''),
      'trxn_id must not be overwritten while another writer holds the lock');

    $this->assertTrue($result->retry, 'Contended lock should flag the event for retry');
    $this->assertFalse($result->ok, 'A retryable event has not been processed, so is not ok');
  }

  /**
   * A retry result returns the queued row to 'new' so the next run picks it
   * up, rather than marking it terminally success or error.
   */
  public function testRetryResultReturnsWebhookEventToTheQueue(): void {
    $webhookEvent = $this->createQueuedWebhookEvent();

    $ipn = $this->retryingIPN();
    $this->assertFalse($ipn->processQueuedWebhookEvent($webhookEvent));

    $row = $this->getWebhookEvent($webhookEvent['id']);
    $this->assertEquals('new', $row['status'], 'Event should be queued for another attempt');
    $this->assertEmpty($row['processed_date'], 'A retryable event has not been processed');
  }

  /**
   * Retrying forever would fail silently, so an event still contended after
   * the staleness threshold is recorded as an error instead.
   */
  public function testRetryResultOnStaleWebhookEventIsRecordedAsError(): void {
    $webhookEvent = $this->createQueuedWebhookEvent('-2 hours');

    $ipn = $this->retryingIPN();
    $this->assertFalse($ipn->processQueuedWebhookEvent($webhookEvent));

    $row = $this->getWebhookEvent($webhookEvent['id']);
    $this->assertEquals('error', $row['status'], 'A stale contended event should stop retrying');
    $this->assertNotEmpty($row['processed_date'], 'An event we have given up on has been processed');
  }

  /**
   * An event that is old but has not yet reached the threshold still retries.
   */
  public function testRetryResultOnRecentWebhookEventStillRetries(): void {
    $webhookEvent = $this->createQueuedWebhookEvent('-30 minutes');

    $ipn = $this->retryingIPN();
    $ipn->processQueuedWebhookEvent($webhookEvent);

    $row = $this->getWebhookEvent($webhookEvent['id']);
    $this->assertEquals('new', $row['status'], 'Event is not yet stale so should still retry');
  }

  /**
   * Take the contribution lock on a separate MySQL connection, so that the
   * connection under test genuinely cannot acquire it.
   *
   * @param int $contributionID
   */
  private function holdContributionLock(int $contributionID): void {
    // Ask core for the lock id rather than recomputing its naming scheme here.
    $lock = new CRM_Core_Lock('data.contribute.contribution.' . $contributionID);
    $idProperty = new ReflectionProperty(CRM_Core_Lock::class, '_id');
    $idProperty->setAccessible(TRUE);
    $lockID = $idProperty->getValue($lock);

    $dsn = DB::parseDSN(CRM_Utils_SQL::autoSwitchDSN(CRM_Core_Config::singleton()->dsn));
    $this->lockHolder = new \mysqli(
      $dsn['hostspec'],
      $dsn['username'],
      $dsn['password'],
      $dsn['database'],
      empty($dsn['port']) ? NULL : (int) $dsn['port']
    );

    $result = $this->lockHolder->query("SELECT GET_LOCK('" . $this->lockHolder->real_escape_string($lockID) . "', 0)");
    $this->assertEquals(1, (int) $result->fetch_row()[0], 'Test setup failed to take the lock');
  }

  /**
   * @param string|null $createdOffset A strtotime() offset for created_date.
   *
   * @return array The created PaymentprocessorWebhook row.
   */
  private function createQueuedWebhookEvent(?string $createdOffset = NULL): array {
    $create = PaymentprocessorWebhook::create(FALSE)
      ->addValue('payment_processor_id', $this->paymentProcessorID)
      ->addValue('event_id', 'evt_mock')
      ->addValue('trigger', 'charge.refunded')
      ->addValue('status', 'processing')
      ->addValue('identifier', 'ch_mock::')
      ->addValue('data', '');

    if ($createdOffset !== NULL) {
      $create->addValue('created_date', date('YmdHis', strtotime($createdOffset)));
    }

    return $create->execute()->first();
  }

  /**
   * @param int $id
   *
   * @return array
   */
  private function getWebhookEvent(int $id): array {
    return PaymentprocessorWebhook::get(FALSE)
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();
  }

  /**
   * As TestBase::simulateEvent(), but returns the whole result object so that
   * the retry flag can be inspected rather than just ->ok.
   *
   * @param array $eventData
   *
   * @return \stdClass
   */
  private function simulateEventReturningResult(array $eventData): \stdClass {
    $stripeClient = $this->paymentObject->stripeClient;
    $stripeClient->events = $this->createMock('Stripe\\Service\\EventService');

    $mockEvent = \Stripe\Event::constructFrom($eventData);
    $stripeClient->events
      ->method('retrieve')
      ->with($this->equalTo($eventData['id']))
      ->willReturn($mockEvent);

    $ipnClass = new CRM_Core_Payment_StripeIPN($this->paymentObject);
    $ipnClass->setEventID($mockEvent->id);
    $ipnClass->setEventType($mockEvent->type);
    $ipnClass->setVerifyData(FALSE);
    $ipnClass->setData($mockEvent->data);
    $ipnClass->setExceptionMode(FALSE);

    return $ipnClass->processWebhookEvent();
  }

  /**
   * An IPN handler that behaves as though it lost a race for the contribution
   * lock, so that the queue-status handling can be tested without having to
   * provoke real contention.
   *
   * @return \CRM_Core_Payment_StripeIPN
   */
  private function retryingIPN(): CRM_Core_Payment_StripeIPN {
    $ipn = $this->getMockBuilder(CRM_Core_Payment_StripeIPN::class)
      ->setConstructorArgs([$this->paymentObject])
      ->onlyMethods(['processWebhookEvent'])
      ->getMock();

    $ipn->method('processWebhookEvent')->willReturn((object) [
      'message' => 'Could not acquire lock',
      'ok' => FALSE,
      'exception' => NULL,
      'retry' => TRUE,
    ]);

    return $ipn;
  }

}
