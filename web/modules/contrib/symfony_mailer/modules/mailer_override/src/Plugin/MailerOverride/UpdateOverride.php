<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\MailerOverride;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_override\Attribute\Override;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\mailer_override\OverrideBase;
use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Defines the Override plug-in for update module.
 */
#[Override(
  id: "update",
  import: new TranslatableMarkup("Update notification addresses"),
  config_overrides: [
    "update.settings" => [
      "notification" => ["emails" => ["dummy@example.com"]],
    ],
  ],
  form_alter: [
    "update_settings" => [
      "remove" => ["update_notify_emails"],
    ],
  ],
)]
class UpdateOverride extends OverrideBase {

  /**
   * {@inheritdoc}
   */
  protected function fromArray(array $message): bool {
    return $this->mailer->notify();
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {
    // Get without overrides to avoid the dummy value set by
    // MailerConfigOverride.
    $mail_notification = implode(',', $helper->config()->get('update.settings')->getOriginal('notification.emails', FALSE));

    if ($mail_notification) {
      $notification_policy = $helper->policyFromAddresses($helper->parseAddress($mail_notification));
      $config['email_to'] = $notification_policy;
      MailerPolicy::import("update.status_notify", $config);
    }
  }

}
