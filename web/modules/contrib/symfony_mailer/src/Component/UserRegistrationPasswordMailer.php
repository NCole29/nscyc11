<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Mailer plug-in for user registration password module.
 */
#[MailerInfo(
  base_tag: "user_registrationpassword",
  sub_defs: [
    "register_confirmation_with_pass" => new TranslatableMarkup("Welcome (no approval required, password is set)"),
  ],
  token_types: ["user"],
)]
class UserRegistrationPasswordMailer extends UserMailer {

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    $email->setParam('token_options', ['callback' => 'user_registrationpassword_mail_tokens', 'clear' => TRUE]);
  }

}
