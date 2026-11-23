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

use Civi\Api4\PaymentProcessor;
use Civi\Api4\StripeWebhook;
use CRM_Stripe_ExtensionUtil as E;

/**
 * Class CRM_Stripe_Webhook
 */
class CRM_Stripe_Webhook {

  /**
   * Checks whether the payment processors have a correctly configured webhook
   *
   * @see stripe_civicrm_check()
   *
   * @param array $messages
   * @param bool $attemptFix If TRUE, try to fix the webhook.
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function check(array &$messages, bool $attemptFix = FALSE): void {
    $env = \Civi::settings()->get('environment');
    if ($env && $env !== 'Production') {
      return;
    }
    $paymentProcessors = PaymentProcessor::get(FALSE)
      ->addWhere('class_name', 'LIKE', 'Payment_Stripe%')
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('domain_id', '=', 'current_domain')
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute();

    foreach ($paymentProcessors as $paymentProcessor) {
      $webhook_path = CRM_Mjwshared_Webhook::getWebhookPath($paymentProcessor['id']);

      try {
        $webhooks = iterator_to_array(
          StripeWebhook::getFromStripe(FALSE)
            ->setPaymentProcessorID($paymentProcessor['id'])
            ->execute()
        );
      }
      catch (Throwable $e) {
        $error = $e->getMessage();
        $messages[] = new CRM_Utils_Check_Message(
          __FUNCTION__ . $paymentProcessor['id'] . 'stripe_webhook',
          $error,
          $this->getTitle($paymentProcessor),
          \Psr\Log\LogLevel::ERROR,
          'fa-money'
              );
        continue;
      }

      $found_wh = FALSE;
      foreach ($webhooks as $wh) {
        if ($wh->status === 'disabled') {
          continue;
        }
        if ($wh->url === $webhook_path) {
          $found_wh = TRUE;
          // Check and update webhook
          try {
            $enabledEvents = self::checkEnabledWebhookEvents($wh);

            if ($wh->api_version !== \Civi\Stripe\Check::API_VERSION) {
              // Add message about API version.
              $messages[] = new CRM_Utils_Check_Message(
                __FUNCTION__ . $paymentProcessor['id'] . 'stripe_webhook',
                E::ts('Webhook API version is set to %2 but CiviCRM requires %3. To correct this please delete the webhook at Stripe and then revisit this page which will recreate it correctly. <em>Webhook path is: <a href="%1" target="_blank">%1</a>.</em>',
                  [
                    1 => urldecode($webhook_path),
                    2 => $wh->api_version,
                    3 => \Civi\Stripe\Check::API_VERSION,
                  ]
                ),
                $this->getTitle($paymentProcessor),
                \Psr\Log\LogLevel::WARNING,
                'fa-money'
              );
            }

            if (!empty($enabledEvents) || ($wh->api_version !== \Civi\Stripe\Check::API_VERSION)) {
              if ($attemptFix) {
                try {
                  // We should try to update the webhook. Pass through the webhook
                  // list we already fetched above to avoid a redundant Stripe API call.
                  StripeWebhook::update(FALSE)
                    ->setPaymentProcessorID($paymentProcessor['id'])
                    ->setWebhooks($webhooks)
                    ->execute();
                  \Civi::log('stripe')->info('CRM_Stripe_Webhook::check: fixed webhook for paymentProcessorID ' . $paymentProcessor['id']);
                }
                catch (\Throwable $e) {
                  \Civi::log('stripe')->error('CRM_Stripe_Webhook::check: failed to update webhook for paymentProcessorID ' . $paymentProcessor['id'] . ': ' . $e->getMessage());
                  $messages[] = new CRM_Utils_Check_Message(
                    __FUNCTION__ . $paymentProcessor['id'] . 'stripe_webhook',
                    E::ts('Unable to update the webhook %1. To correct this please delete the webhook at Stripe and then revisit this page which will recreate it correctly. Error was: %2',
                      [
                        1 => urldecode($webhook_path),
                        2 => htmlspecialchars($e->getMessage()),
                      ]
                    ),
                    $this->getTitle($paymentProcessor),
                    \Psr\Log\LogLevel::WARNING,
                    'fa-money'
                  );
                }
              }
              else {
                $message = new CRM_Utils_Check_Message(
                  __FUNCTION__ . $paymentProcessor['id'] . 'stripe_webhook',
                  E::ts('Problems detected with Stripe webhook! <em>Webhook path is: <a href="%1" target="_blank">%1</a>.</em>',
                    [1 => urldecode($webhook_path)]
                  ),
                  $this->getTitle($paymentProcessor),
                  \Psr\Log\LogLevel::WARNING,
                  'fa-money'
                );
                $message->addAction(
                  E::ts('View and fix problems'),
                  NULL,
                  'href',
                  ['path' => 'civicrm/stripe/fix-webhook', 'query' => ['reset' => 1]]
                );
                $messages[] = $message;
              }
            }
          }
          catch (\Throwable $e) {
            \Civi::log('stripe')->error('CRM_Stripe_Webhook::check: error checking/updating webhook for paymentProcessorID ' . $paymentProcessor['id'] . ': ' . $e->getMessage());
            $messages[] = new CRM_Utils_Check_Message(
              __FUNCTION__ . $paymentProcessor['id'] . 'stripe_webhook',
              E::ts('Could not check/update existing webhooks, got error from stripe <em>%1</em>', [
                1 => htmlspecialchars($e->getMessage()),
              ]
              ),
              $this->getTitle($paymentProcessor),
              \Psr\Log\LogLevel::WARNING,
              'fa-money'
            );
          }
        }
      }

      if (!$found_wh) {
        if ($attemptFix) {
          try {
            // Try to create one.
            StripeWebhook::create(FALSE)
              ->setPaymentProcessorID($paymentProcessor['id'])
              ->execute();
            \Civi::log('stripe')->info('CRM_Stripe_Webhook::check: created webhook for paymentProcessorID ' . $paymentProcessor['id']);
          }
          catch (\Throwable $e) {
            \Civi::log('stripe')->error('CRM_Stripe_Webhook::check: failed to create webhook for paymentProcessorID ' . $paymentProcessor['id'] . ': ' . $e->getMessage());
            $messages[] = new CRM_Utils_Check_Message(
              __FUNCTION__ . $paymentProcessor['id'] . 'stripe_webhook',
              E::ts('Could not create webhook, got error from stripe <em>%1</em>', [
                1 => htmlspecialchars($e->getMessage()),
              ]),
              $this->getTitle($paymentProcessor),
              \Psr\Log\LogLevel::WARNING,
              'fa-money'
            );
          }
        }
        else {
          $message = new CRM_Utils_Check_Message(
            __FUNCTION__ . $paymentProcessor['id'] . 'stripe_webhook',
            E::ts(
              'Stripe Webhook missing or needs update! <em>Expected webhook path is: <a href="%1" target="_blank">%1</a></em>',
              [1 => $webhook_path]
            ),
            $this->getTitle($paymentProcessor),
            \Psr\Log\LogLevel::WARNING,
            'fa-money'
          );
          $message->addAction(
            E::ts('View and fix problems'),
            NULL,
            'href',
            ['path' => 'civicrm/stripe/fix-webhook', 'query' => ['reset' => 1]]
          );
          $messages[] = $message;
        }
      }
    }
  }

  /**
   * Get the error message title for the system check
   * @param array $paymentProcessor
   *
   * @return string
   */
  private function getTitle(array $paymentProcessor): string {
    if (!empty($paymentProcessor['is_test'])) {
      $paymentProcessor['name'] .= ' (test)';
    }
    return E::ts('Stripe Payment Processor: %1 (%2)', [
      1 => $paymentProcessor['name'],
      2 => $paymentProcessor['id'],
    ]);
  }

  /**
   * Check existing webhook to see if it has the right set of enabled events
   *
   * @param \Stripe\WebhookEndpoint $webhook
   *
   * @return array of enabled events if different from current webhook. Empty array if it's OK.
   */
  public static function checkEnabledWebhookEvents(\Stripe\WebhookEndpoint $webhook): array {
    if (array_diff(self::getDefaultEnabledEvents(), $webhook->enabled_events)) {
      $enabledEvents = self::getDefaultEnabledEvents();
    }

    return $enabledEvents ?? [];
  }

  /**
   * Given all webhook endpoints already filtered to belong to one payment
   * processor's URL, decide which one to keep, which (if any) to disable as
   * a brief rollback fallback, and which to delete outright.
   *
   * Only one endpoint is ever left disabled at a time - anything older is
   * deleted so we don't accumulate cruft against Stripe's 16-webhook-per-account
   * limit. Already-disabled endpoints are considered too, so old disabled
   * duplicates left over from a previous bug get cleaned up as well.
   *
   * @param \Stripe\WebhookEndpoint[] $webhooks
   *
   * @return array{keep: ?\Stripe\WebhookEndpoint, disable: ?\Stripe\WebhookEndpoint, delete: \Stripe\WebhookEndpoint[]}
   */
  public static function classifyWebhooksForProcessor(array $webhooks): array {
    $valid = [];
    $stale = [];
    foreach ($webhooks as $webhook) {
      if (($webhook->api_version === \Civi\Stripe\Check::API_VERSION)
        && ($webhook->status === 'enabled')
        && empty(self::checkEnabledWebhookEvents($webhook))) {
        $valid[] = $webhook;
      }
      else {
        $stale[] = $webhook;
      }
    }

    // If more than one valid webhook exists, keep only the newest; treat the rest as stale.
    usort($valid, fn($a, $b) => $b->created <=> $a->created);
    $keep = array_shift($valid);
    $stale = array_merge($stale, $valid);

    usort($stale, fn($a, $b) => $b->created <=> $a->created);
    // Keep the newest stale webhook disabled as a brief rollback fallback; delete the rest.
    $disable = array_shift($stale);

    return ['keep' => $keep, 'disable' => $disable, 'delete' => $stale];
  }

  /**
   * List of webhooks we currently handle
   *
   * @return array
   */
  public static function getDefaultEnabledEvents(): array {
    return [
      'invoice.finalized',
    // Ignore this event because it sometimes causes duplicates (it's sent at almost the same time as invoice.payment_succeeded
      'invoice.paid',
      //   and if they are both processed at the same time the check to see if the payment already exists is missed and it gets created twice.
      'invoice.payment_succeeded',
      'invoice.payment_failed',
      'charge.failed',
      'charge.refunded',
      'charge.succeeded',
      'charge.captured',
      'customer.subscription.updated',
      'customer.subscription.deleted',
      'checkout.session.completed',
      // These replace charge.succeeded and charge.failed.
      // 'payment_intent.succeeded',
      // 'payment_intent.payment_failed',
    ];
  }

  /**
   * List of webhooks that we do NOT process immediately.
   *
   * @return array
   */
  public static function getDelayProcessingEvents(): array {
    return [
      // This event does not need processing in real-time because it will be received simultaneously with
      //   `invoice.payment_succeeded` if start date is "now".
      // If starting a subscription on a specific date we only receive this event until the date the invoice is
      // actually due for payment.
      // If we allow it to process whichever gets in first (invoice.finalized or invoice.payment_succeeded) we will get
      //   delays in completing payments/sending receipts until the scheduled job is run.
      'invoice.finalized',
    ];
  }

}
