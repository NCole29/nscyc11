<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\MailerOverride;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_override\Attribute\Override;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\mailer_override\OverrideBase;
use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Defines the Override plug-in for simplenews module subscriber emails.
 *
 * Replaces parts of:
 * - \Drupal\simplenews\Mail\MailBuilder
 * - \Drupal\simplenews\Mail\Mailer.
 */
#[Override(
  id: "simplenews.subscriber",
  override: ["simplenews.subscribe_combined", "simplenews.validate"],
  import: new TranslatableMarkup("Simplenews subscriber settings"),
  import_warning: new TranslatableMarkup("This overrides the default HTML messages with imported plain text versions"),
  form_alter: [
    "simplenews_admin_settings_newsletter" => [
      "remove" => ["simplenews_default_options", "simplenews_sender_info"],
      "tag" => NULL,
    ],
    "simplenews_admin_settings_subscription" => [
      "remove" => ["subscription_mail"],
    ],
  ],
)]
class SimplenewsSubscriberOverride extends OverrideBase {

  /**
   * {@inheritdoc}
   */
  protected function fromArray(array $message): bool {
    $operation = ($message['key'] == 'subscribe_combined') ? 'subscribe' : 'validate';
    return $this->mailer->sendToSubscriber($operation, $message['params']['context']['simplenews_subscriber']);
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {
    $subscription = $helper->config()->get('simplenews.settings')->get('subscription');

    $convert = [
      'confirm_combined' => 'subscribe',
      'validate' => 'validate',
    ];

    foreach ($convert as $from => $to) {
      $config = [
        'email_subject' => ['value' => $subscription["{$from}_subject"]],
        'email_body' => $helper->policyFromPlainBody($subscription["{$from}_body"]),
      ];
      MailerPolicy::import("simplenews.subscriber.$to", $config);
    }
  }

}
