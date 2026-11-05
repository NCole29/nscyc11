<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\symfony_mailer\Attachment;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Mailer plug-in for verification emails.
 */
#[MailerInfo(
  base_tag: "symfony_mailer",
  sub_defs: ["verify" => new TranslatableMarkup("Verification email")],
  required_config: ["email_subject", "email_body"],
  token_types: ["site"],
  variables: [
    'logo_url' => new TranslatableMarkup("Logo URL"),
    'day' => new TranslatableMarkup("Day of the week"),
  ],
)]
class VerifyMailer extends ComponentMailerBase implements VerifyMailerInterface {

  /**
   * {@inheritdoc}
   */
  public function verify($to): bool {
    return $this->newEmail('verify')
      ->setTo($to)
      ->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $logo = \Drupal::service('extension.list.module')->getPath('symfony_mailer') . '/logo.png';
    $logo_uri = \Drupal::service('file_url_generator')->generateString($logo);

    // - Add a custom CSS library, defined in symfony_mailer.libraries.yml.
    // - The CSS is defined in verify.email.css.
    // - Set variables, used by the mailer policy defined in
    //   mailer_policy.mailer_policy.symfony_mailer.verify.yml.
    // - Add an attachment.
    $email->addLibrary('symfony_mailer/verify')
      ->attach(Attachment::fromPath($logo_uri, isUri: TRUE))
      ->setVariable('logo_url', $logo_uri)
      ->setVariable('day', date("l"));
  }

}
