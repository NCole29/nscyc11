<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\MailerOverride;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_override\Attribute\Override;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\mailer_override\OverrideBase;
use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Defines the Override plug-in for user module.
 */
#[Override(
  id: "user",
  import: new TranslatableMarkup("User email settings"),
  import_warning: new TranslatableMarkup("This overrides the default HTML messages with imported plain text versions"),
  config_overrides: [
    "user.settings" => [
      "notify" => [
        "cancel_confirm" => TRUE,
        "password_reset" => TRUE,
        "status_activated" => TRUE,
        "status_blocked" => TRUE,
        "status_canceled" => TRUE,
        "register_admin_created" => TRUE,
        "register_no_approval_required" => TRUE,
        "register_pending_approval" => TRUE,
      ],
    ],
  ],
  form_alter: [
    "user_admin_settings" => [
      "remove" => [
        "mail_notification_address",
        "email_admin_created",
        "email_pending_approval",
        "email_pending_approval_admin",
        "email_no_approval_required",
        "email_password_reset",
        "email_activated",
        "email_blocked",
        "email_cancel_confirm",
        "email_canceled",
      ],
    ],
  ],
)]
class UserOverride extends OverrideBase {

  /**
   * {@inheritdoc}
   */
  protected function fromArray(array $message): bool {
    if ($message['key'] != 'register_pending_approval_admin') {
      return $this->mailer->notify($message['key'], $message['params']['account']);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {
    $config_factory = $helper->config();
    $notify = $config_factory->get('user.settings')->get('notify');
    $mail = $config_factory->get('user.mail')->get();
    unset($mail['langcode']);
    unset($mail['_core']);

    if ($mail_notification = $config_factory->get('system.site')->get('mail_notification')) {
      $notification_policy = $helper->policyFromAddresses($helper->parseAddress($mail_notification));
      $config['email_from'] = $notification_policy;
      MailerPolicy::import("user", $config);
    }

    foreach ($mail as $sub_type => $values) {
      $config = [
        'email_subject' => ['value' => $values["subject"]],
        'email_body' => $helper->policyFromPlainBody($values["body"]),
      ];
      if (isset($notify[$sub_type]) && !$notify[$sub_type]) {
        $config['email_skip_sending']['message'] = 'Notification disabled in settings';
      }
      if (($sub_type == 'register_pending_approval_admin') && isset($notification_policy)) {
        $config['email_to'] = $notification_policy;
      }
      MailerPolicy::import("user.$sub_type", $config);
    }
  }

}
