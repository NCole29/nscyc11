<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simplenews\SubscriberInterface;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Mailer plug-in for simplenews module newsletter emails.
 *
 * Replaces \Drupal\simplenews\Mail\*
 */
#[MailerInfo(
  base_tag: "simplenews.newsletter",
  sub_defs: [
    "node" => new TranslatableMarkup("Issue"),
  ],
  metadata_key: "simplenews_newsletter",
  required_config: ["email_subject", "email_body"],
  token_types: ["simplenews-subscriber", "simplenews-newsletter"],
  variables: [
    'issue' => new TranslatableMarkup("Issue entity object"),
    'opt_out_hidden' => new TranslatableMarkup("Whether to hide opt-out"),
    'reason' => new TranslatableMarkup("Reason for hiding opt-out"),
    'mode' => new TranslatableMarkup(
      "Mode, one of: '@node' = normal bulk send, '@test' = test message, '@extra' = extra copy",
       ['@node' => 'node', '@test' => 'test', '@extra' => 'extra'],
    ),
  ],
)]
class SimplenewsNewsletterMailer extends ComponentMailerBase implements SimplenewsNewsletterMailerInterface {

  /**
   * {@inheritdoc}
   */
  public function sendIssue(ContentEntityInterface $issue, SubscriberInterface $subscriber, string $mode): bool {
    $address = new Address($subscriber->getMail(), '', $subscriber->getLangcode(), $subscriber->getUser());
    $token_data = [
      // Non-standard key (!= entity type).
      'newsletter' => $issue->simplenews_issue->entity,
      'simplenews_subscriber' => NULL,
      $issue->getEntityTypeId() => $issue,
    ];

    return $this->newEmail('node')
      ->setEntityParam($issue->simplenews_issue->entity)
      ->setParam('issue', $issue)
      ->setEntityParam($subscriber)
      ->setParam('token_data', $token_data)
      ->setVariable('mode', $mode)
      ->setTo($address)
      ->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $newsletter = $email->getParam('simplenews_newsletter');
    $mode = $email->getVariables()['mode'];
    $temp_subscriber = $mode && !$email->getParam('simplenews_subscriber')->id();
    // Match existing view mode from simplenews module.
    $email->setEntityVariable('issue', 'email_html')
      ->addTextHeader('Precedence', 'bulk')
      ->setVariable('opt_out_hidden', !$newsletter->isAccessible() || $temp_subscriber)
      ->setVariable('reason', $newsletter->reason ?? '');

    // @todo Create SubscriberInterface::getUnsubscribeUrl().
    if ($unsubscribe_url = \Drupal::token()->replace('[simplenews-subscriber:unsubscribe-url]', $email->getParams(), ['clear' => TRUE])) {
      $email->addTextHeader('List-Unsubscribe', "<$unsubscribe_url>");
    }
  }

}
