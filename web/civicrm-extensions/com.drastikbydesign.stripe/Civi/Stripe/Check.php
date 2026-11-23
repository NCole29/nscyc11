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

namespace Civi\Stripe;

use Civi\Core\Service\AutoSubscriber;
use CRM_Stripe_ExtensionUtil as E;

class Check extends AutoSubscriber {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents(): array {
    return [
      '&hook_civicrm_check' => 'checkRequirements',
    ];
  }

  /**
   * @var string
   */
  const API_VERSION = \Stripe\Util\ApiVersion::CURRENT;
  const MIN_VERSION_MJWSHARED = '1.6.1';
  const MIN_VERSION_FIREWALL = '1.5.11';

  /**
   * @var array
   */
  private array $messages;

  /**
   * Implements hook_civicrm_check().
   *
   * @throws \CRM_Core_Exception
   */
  public function checkRequirements(&$messages): void {
    $this->messages = $messages;
    $this->checkExtensionMjwshared();
    $this->checkExtensionFirewall();
    $this->checkWebhooks();
    $this->checkFailedPaymentIntents();
    $this->checkOrphanedSucceededPaymentIntents();
    $this->checkContributionsWithUnrecordedStripePayments();
    $messages = $this->messages;
  }

  /**
   * @param string $extensionName
   * @param string $minVersion
   * @param string $actualVersion
   */
  private function requireExtensionMinVersion(string $extensionName, string $minVersion, string $actualVersion) {
    $actualVersionModified = $actualVersion;
    if (substr($actualVersion, -4) === '-dev') {
      $actualVersionModified = substr($actualVersion, 0, -4);
      $devMessageAlreadyDefined = FALSE;
      foreach ($this->messages as $message) {
        if ($message->getName() === __FUNCTION__ . $extensionName . '_requirements_dev') {
          // Another extension already generated the "Development version" message for this extension
          $devMessageAlreadyDefined = TRUE;
        }
      }
      if (!$devMessageAlreadyDefined) {
        $message = new \CRM_Utils_Check_Message(
          __FUNCTION__ . $extensionName . '_requirements_dev',
          E::ts('You are using a development version of %1 extension.',
            [1 => $extensionName]),
          E::ts('%1: Development version', [1 => $extensionName]),
          \Psr\Log\LogLevel::WARNING,
          'fa-code'
        );
        $this->messages[] = $message;
      }
    }

    if (version_compare($actualVersionModified, $minVersion) === -1) {
      $message = new \CRM_Utils_Check_Message(
        __FUNCTION__ . $extensionName . E::SHORT_NAME . '_requirements',
        E::ts('The %1 extension requires the %2 extension version %3 or greater but your system has version %4.',
          [
            1 => ucfirst(E::SHORT_NAME),
            2 => $extensionName,
            3 => $minVersion,
            4 => $actualVersion,
          ]),
        E::ts('%1: Missing Requirements', [1 => ucfirst(E::SHORT_NAME)]),
        \Psr\Log\LogLevel::ERROR,
        'fa-exclamation-triangle'
      );
      $message->addAction(
        E::ts('Upgrade %1 now', [1 => $extensionName]),
        NULL,
        'href',
        ['path' => 'civicrm/admin/extensions', 'query' => ['action' => 'update', 'id' => $extensionName, 'key' => $extensionName]]
      );
      $this->messages[] = $message;
    }
  }

  /**
   * @throws \CRM_Core_Exception
   */
  private function checkExtensionMjwshared() {
    // mjwshared: required. Requires min version
    $extensionKey = 'mjwshared';
    $extensionName = 'Payment Shared (MJWshared)';
    $extension = \Civi\Api4\Extension::get(FALSE)
      ->addSelect('status', 'version')
      ->addWhere('key', '=', $extensionKey)
      ->execute()
      ->first();

    if (empty($extension) || ($extension['status'] !== 'installed')) {
      $message = new \CRM_Utils_Check_Message(
        __FUNCTION__ . E::SHORT_NAME . '_requirements',
        E::ts('The <em>%1</em> extension requires the <em>Payment Shared</em> extension which is not installed. See <a href="%2" target="_blank">details</a> for more information.',
          [
            1 => ucfirst(E::SHORT_NAME),
            2 => 'https://civicrm.org/extensions/mjwshared',
          ]
        ),
        E::ts('%1: Missing Requirements', [1 => ucfirst(E::SHORT_NAME)]),
        \Psr\Log\LogLevel::ERROR,
        'fa-money'
      );
      $message->addAction(
        E::ts('Install now'),
        NULL,
        'href',
        ['path' => 'civicrm/admin/extensions', 'query' => ['action' => 'update', 'id' => $extensionKey, 'key' => $extensionKey]]
      );
      $this->messages[] = $message;
    }
    else {
      $this->requireExtensionMinVersion($extensionName, self::MIN_VERSION_MJWSHARED, $extension['version']);
    }
  }

  /**
   * @return void
   * @throws \CRM_Core_Exception
   */
  private function checkExtensionFirewall(): void {
    $extensionName = 'firewall';

    $extensions = civicrm_api3('Extension', 'get', [
      'full_name' => $extensionName,
    ]);

    if (empty($extensions['count']) || ($extensions['values'][$extensions['id']]['status'] !== 'installed')) {
      $message = new \CRM_Utils_Check_Message(
        __FUNCTION__ . 'stripe_recommended',
        E::ts('If you are using Stripe to accept payments on public forms (eg. contribution/event registration forms) it is required that you install the <strong><a href="https://lab.civicrm.org/extensions/firewall">firewall</a></strong> extension. Some sites have become targets for spammers who use the payment endpoint to try and test credit cards by submitting invalid payments to your Stripe account.'),
        E::ts('Required Extension: firewall'),
        \Psr\Log\LogLevel::ERROR,
        'fa-lightbulb-o'
      );
      $message->addAction(
        E::ts('Install now'),
        NULL,
        'href',
        ['path' => 'civicrm/admin/extensions', 'query' => ['action' => 'update', 'id' => $extensionName, 'key' => $extensionName]]
      );
      $this->messages[] = $message;
    }
    if (isset($extensions['id']) && $extensions['values'][$extensions['id']]['status'] === 'installed') {
      $this->requireExtensionMinVersion($extensionName, self::MIN_VERSION_FIREWALL, $extensions['values'][$extensions['id']]['version']);
    }
  }

  /**
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function checkWebhooks(): void {
    // If we didn't install mjwshared yet check requirements but don't crash when checking webhooks
    if (method_exists('CRM_Mjwshared_Webhook', 'getWebhookPath')) {
      $webhooks = new \CRM_Stripe_Webhook();
      $webhooks->check($this->messages);
    }
  }

  /**
   * Try to detect if a client is being spammed / credit card fraud.
   *
   * @return void
   * @throws \CRM_Core_Exception
   */
  private function checkFailedPaymentIntents(): void {
    // Check for a high volume of failed/pending contributions
    $count = \CRM_Core_DAO::singleValueQuery('SELECT count(*)
      FROM civicrm_stripe_paymentintent
      WHERE status = "failed"
        AND TIMESTAMPDIFF(minute, created_date, NOW()) < 60
      ORDER BY id DESC
      LIMIT 1000');

    if ($count > 20) {
      $message = new \CRM_Utils_Check_Message(
        'stripe_paymentintentspam',
        E::ts('%1 failed Stripe Payment Intents in the past hour. Please check the logs. They are problably hitting the CiviCRM REST API.', [1 => $count]),
        E::ts('Stripe - High rate of failed contributions'),
        \Psr\Log\LogLevel::CRITICAL,
        'fa-check'
      );
      $this->messages[] = $message;
    }
    else {
      $message = new \CRM_Utils_Check_Message(
        'stripe_paymentintentspam',
        E::ts('%1 failed Stripe Payment Intents in the past hour.', [1 => $count]) . ' ' . E::ts('We monitor this in case someone malicious is testing stolen credit cards on public contribution forms.'),
        E::ts('Stripe - Failed Stripe Payment Intents'),
        \Psr\Log\LogLevel::INFO,
        'fa-check'
      );
      $this->messages[] = $message;
    }
  }

  /**
   * Detect PaymentIntents that succeeded at Stripe but whose linked
   * Contribution was never completed - the customer was charged but CiviCRM
   * doesn't show it (eg. a missed/delayed webhook, or a checkout retry racing
   * with the original attempt). Not limited to rows corrected by
   * Job.process_stripe's cancel-incomplete cleanup - any such mismatch should
   * surface here.
   *
   * @return void
   * @throws \CRM_Core_Exception
   */
  private function checkOrphanedSucceededPaymentIntents(): void {
    $succeeded = \Civi\Api4\StripePaymentintent::get(FALSE)
      ->addSelect('contribution_id')
      ->addWhere('status', '=', 'succeeded')
      ->addWhere('contribution_id', 'IS NOT EMPTY')
      ->addWhere('payment_processor_id.is_test', '=', FALSE)
      // Give the normal completion paths (browser return, webhook) time to
      // catch up before flagging this as a problem.
      ->addWhere('created_date', '<', '-30 minutes')
      ->execute();

    if (!$succeeded->count()) {
      return;
    }

    $contributionStatuses = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id', 'contribution_status_id:name')
      ->addWhere('id', 'IN', $succeeded->column('contribution_id'))
      ->execute()
      ->indexBy('id');

    $orphanedContributionIDs = [];
    foreach ($succeeded as $paymentintent) {
      $status = $contributionStatuses[$paymentintent['contribution_id']]['contribution_status_id:name'] ?? NULL;
      if (!in_array($status, ['Completed', 'Refunded', 'Partially paid'], TRUE)) {
        $orphanedContributionIDs[] = $paymentintent['contribution_id'];
      }
    }

    if ($orphanedContributionIDs) {
      $message = new \CRM_Utils_Check_Message(
        'stripe_orphaned_succeeded_paymentintent',
        E::ts('%1 payment(s) succeeded at Stripe but the linked Contribution was never completed - the customer was charged and CiviCRM does not show it. Check Contribution ID(s): %2.',
          [1 => count($orphanedContributionIDs), 2 => implode(', ', $orphanedContributionIDs)]
        ),
        E::ts('Stripe - Payment succeeded but not recorded'),
        \Psr\Log\LogLevel::WARNING,
        'fa-exclamation-triangle'
      );
      $this->messages[] = $message;
    }
  }

  /**
   * A Contribution with two or more successful Stripe payments isn't
   * necessarily wrong - a split/partial payment (eg. 30 + 30 for a 60 total)
   * is legitimate. What matters is whether the total actually taken at
   * Stripe, net of any refunds, exceeds what CiviCRM has recorded as paid -
   * if so, one of those payments was never turned into a Payment/FinancialTrxn
   * (eg. the trxn_id race between two Checkout Sessions retried against the
   * same Contribution: the second attempt's completion overwrites the
   * first's match key before its delayed webhook can be reprocessed).
   * `paid_amount` is already net of any refunds CiviCRM has recorded, so the
   * Stripe-side total must be netted the same way or a duplicate charge that
   * has since been correctly refunded would be flagged forever.
   *
   * @return void
   * @throws \CRM_Core_Exception
   */
  private function checkContributionsWithUnrecordedStripePayments(): void {
    $duplicateCandidates = \Civi\Api4\StripePaymentintent::get(FALSE)
      ->addSelect(
        'contribution_id',
        'MIN(payment_processor_id) AS min_payment_processor_id',
        'GROUP_CONCAT(stripe_intent_id) AS intent_ids',
        'COUNT(DISTINCT stripe_intent_id) AS intent_count'
      )
      ->addWhere('status', 'IN', ['succeeded', 'complete'])
      ->addWhere('contribution_id', 'IS NOT EMPTY')
      ->addWhere('stripe_intent_id', 'IS NOT EMPTY')
      ->addWhere('payment_processor_id.is_test', '=', FALSE)
      ->addGroupBy('contribution_id')
      ->addHaving('intent_count', '>', 1)
      ->execute();

    $flaggedContributionIDs = [];
    foreach ($duplicateCandidates as $candidate) {
      $contributionID = (int) $candidate['contribution_id'];

      try {
        $paymentProcessor = \Civi\Payment\System::singleton()->getById((int) $candidate['min_payment_processor_id']);
        $stripeTotal = 0;
        foreach ((array) $candidate['intent_ids'] as $intentID) {
          $intent = $paymentProcessor->stripeClient->paymentIntents->retrieve($intentID, ['expand' => ['latest_charge']]);
          if ($intent->status === 'succeeded') {
            $refunded = $intent->latest_charge->amount_refunded ?? 0;
            $stripeTotal += $intent->amount_received - $refunded;
          }
        }
      }
      catch (\Exception $e) {
        // Can't verify amounts right now (eg. processor misconfigured) -
        // skip rather than risk a false positive.
        continue;
      }

      $contribution = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('currency', 'paid_amount', 'contact_id')
        ->addWhere('id', '=', $contributionID)
        ->execute()
        ->first();
      if (!$contribution) {
        continue;
      }
      $precision = \CRM_Utils_Money::getCurrencyPrecision($contribution['currency']);
      $stripeTotal = round($stripeTotal / 100, $precision);
      $recordedTotal = round((float) $contribution['paid_amount'], $precision);

      if ($stripeTotal > $recordedTotal) {
        $flaggedContributionIDs[$contributionID] = $contribution['contact_id'];
      }
    }

    if ($flaggedContributionIDs) {
      $contributionLinks = [];
      foreach ($flaggedContributionIDs as $contributionID => $contactID) {
        $url = \CRM_Utils_System::url('civicrm/contact/view/contribution', "reset=1&id={$contributionID}&cid={$contactID}&action=view");
        $contributionLinks[] = "<a href=\"{$url}\">{$contributionID}</a>";
      }
      $message = new \CRM_Utils_Check_Message(
        'stripe_unrecorded_duplicate_payment',
        E::ts('%1 Contribution(s) have multiple successful Stripe payments totalling more than what CiviCRM has recorded as paid - check for a duplicate/unrefunded charge. Contribution ID(s): %2.',
          [1 => count($flaggedContributionIDs), 2 => implode(', ', $contributionLinks)]
        ),
        E::ts('Stripe - Possible duplicate payment'),
        \Psr\Log\LogLevel::WARNING,
        'fa-exclamation-triangle'
      );
      $this->messages[] = $message;
    }
  }

}
