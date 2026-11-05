<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplenews\SubscriberInterface;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\Attribute\MailerInfo;

/**
 * Defines the Mailer plug-in for simplenews module subscriber emails.
 *
 * Replaces parts of:
 * - \Drupal\simplenews\Mail\MailBuilder
 * - \Drupal\simplenews\Mail\Mailer.
 */
#[MailerInfo(
  base_tag: "simplenews.subscriber",
  label: new TranslatableMarkup("Simplenews subscriber"),
  sub_defs: [
    "subscribe" => new TranslatableMarkup("Confirmation"),
    "validate" => new TranslatableMarkup("Validate"),
  ],
  required_config: ["email_subject", "email_body"],
  token_types: ["simplenews-subscriber"],
)]
class SimplenewsSubscriberMailer extends ComponentMailerBase implements SimplenewsSubscriberMailerInterface {

  /**
   * {@inheritdoc}
   */
  public function sendToSubscriber(string $operation, SubscriberInterface $subscriber): bool {
    $address = new Address($subscriber->getMail(), '', $subscriber->getLangcode(), $subscriber->getUser());
    return $this->newEmail($operation)
      ->setEntityParam($subscriber)
      // Non-standard token type (!= entity type).
      ->setParam('token_data', ['simplenews_subscriber' => NULL])
      ->setTo($address)
      ->send();
  }

}
