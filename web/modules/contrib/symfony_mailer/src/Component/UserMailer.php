<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Mailer plug-in for user module.
 *
 * Replaces _user_mail_notify().
 */
#[MailerInfo(
  base_tag: "user",
  sub_defs: [
    "cancel_confirm" => new TranslatableMarkup("Account cancellation confirmation"),
    "password_reset" => new TranslatableMarkup("Password recovery"),
    "register_admin_created" => new TranslatableMarkup("Account created by administrator"),
    "register_no_approval_required" => new TranslatableMarkup("Registration confirmation (No approval required)"),
    "register_pending_approval" => new TranslatableMarkup("Registration confirmation (Pending approval)"),
    "register_pending_approval_admin" => new TranslatableMarkup("Admin (user awaiting approval)"),
    "status_activated" => new TranslatableMarkup("Account activation"),
    "status_blocked" => new TranslatableMarkup("Account blocked"),
    "status_canceled" => new TranslatableMarkup("Account cancelled"),
  ],
  required_config: ["email_subject", "email_body"],
  token_types: ["user"],
)]
class UserMailer extends ComponentMailerBase implements UserMailerInterface {

  /**
   * {@inheritdoc}
   */
  public function notify(string $op, UserInterface $user): bool {
    if ($op == 'register_pending_approval') {
      $this->newEmail("{$op}_admin")->setEntityParam($user)->send();
    }

    if (!$user->getEmail()) {
      \Drupal::logger('user')->info('Could not send a user email for the operation "%op" because the user account %account does not have an email address.', [
        '%op' => $op,
        '%account' => $user->getDisplayName(),
      ]);
      return FALSE;
    }

    return $this->newEmail($op)
      ->setEntityParam($user)
      ->setTo($user)
      ->send();
  }

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    $email->setParam('token_options', ['callback' => 'user_mail_tokens', 'clear' => TRUE]);
  }

}
