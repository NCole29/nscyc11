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
 * Unit tests for CRM_Stripe_Webhook::classifyWebhooksForProcessor().
 * This is a pure function (no Stripe API calls) so these tests use plain
 * \Stripe\WebhookEndpoint objects rather than mocking the Stripe client.
 *
 * @group headless
 */
require_once 'TestBase.php';
class CRM_Stripe_WebhookTest extends CRM_Stripe_TestBase {

  public function setUp(): void {
    parent::setUp();
  }

  public function tearDown(): void {
    parent::tearDown();
  }

  /**
   * @param array $overrides
   *
   * @return \Stripe\WebhookEndpoint
   */
  private function makeWebhook(array $overrides = []): \Stripe\WebhookEndpoint {
    $webhook = new \Stripe\WebhookEndpoint($overrides['id'] ?? 'we_mock');
    $webhook->url = $overrides['url'] ?? 'https://example.org/civicrm/payment/ipn/1';
    $webhook->api_version = $overrides['api_version'] ?? \Civi\Stripe\Check::API_VERSION;
    $webhook->status = $overrides['status'] ?? 'enabled';
    $webhook->enabled_events = $overrides['enabled_events'] ?? CRM_Stripe_Webhook::getDefaultEnabledEvents();
    $webhook->created = $overrides['created'] ?? time();
    return $webhook;
  }

  public function testSingleCorrectWebhookIsKept() {
    $webhook = $this->makeWebhook();
    $classified = CRM_Stripe_Webhook::classifyWebhooksForProcessor([$webhook]);

    $this->assertSame($webhook, $classified['keep']);
    $this->assertNull($classified['disable']);
    $this->assertSame([], $classified['delete']);
  }

  public function testNoWebhooksMeansNoneToKeep() {
    $classified = CRM_Stripe_Webhook::classifyWebhooksForProcessor([]);

    $this->assertNull($classified['keep']);
    $this->assertNull($classified['disable']);
    $this->assertSame([], $classified['delete']);
  }

  public function testCorrectWebhookPlusOneStaleApiVersion() {
    $correct = $this->makeWebhook(['id' => 'we_correct']);
    $staleApiVersion = $this->makeWebhook(['id' => 'we_stale', 'api_version' => '2019-02-19']);
    $classified = CRM_Stripe_Webhook::classifyWebhooksForProcessor([$correct, $staleApiVersion]);

    $this->assertSame($correct, $classified['keep']);
    $this->assertSame($staleApiVersion, $classified['disable']);
    $this->assertSame([], $classified['delete']);
  }

  public function testNewestStaleIsDisabledOlderStaleIsDeleted() {
    $correct = $this->makeWebhook(['id' => 'we_correct']);
    $olderStale = $this->makeWebhook(['id' => 'we_older_stale', 'api_version' => '2019-02-19', 'created' => time() - 3600]);
    $newerStale = $this->makeWebhook(['id' => 'we_newer_stale', 'api_version' => '2019-02-19', 'created' => time() - 60]);
    $classified = CRM_Stripe_Webhook::classifyWebhooksForProcessor([$correct, $olderStale, $newerStale]);

    $this->assertSame($correct, $classified['keep']);
    $this->assertSame($newerStale, $classified['disable']);
    $this->assertSame([$olderStale], $classified['delete']);
  }

  public function testDuplicateValidWebhooksKeepsNewestOnly() {
    $olderValid = $this->makeWebhook(['id' => 'we_older_valid', 'created' => time() - 3600]);
    $newerValid = $this->makeWebhook(['id' => 'we_newer_valid', 'created' => time() - 60]);
    $classified = CRM_Stripe_Webhook::classifyWebhooksForProcessor([$olderValid, $newerValid]);

    $this->assertSame($newerValid, $classified['keep']);
    $this->assertSame($olderValid, $classified['disable']);
    $this->assertSame([], $classified['delete']);
  }

  public function testAlreadyDisabledStaleWebhookIsCleanedUp() {
    $correct = $this->makeWebhook(['id' => 'we_correct']);
    $olderDisabled = $this->makeWebhook(['id' => 'we_older_disabled', 'status' => 'disabled', 'created' => time() - 7200]);
    $newerDisabled = $this->makeWebhook(['id' => 'we_newer_disabled', 'status' => 'disabled', 'created' => time() - 60]);
    $classified = CRM_Stripe_Webhook::classifyWebhooksForProcessor([$correct, $olderDisabled, $newerDisabled]);

    $this->assertSame($correct, $classified['keep']);
    $this->assertSame($newerDisabled, $classified['disable']);
    $this->assertSame([$olderDisabled], $classified['delete']);
  }

}
