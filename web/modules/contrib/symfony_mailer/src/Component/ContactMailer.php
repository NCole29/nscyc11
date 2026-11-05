<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\contact\MessageInterface;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\user\Entity\User;

/**
 * Defines the Mailer plug-in for contact module page forms.
 *
 * Replaces \Drupal\contact\MailHandler.
 */
#[MailerInfo(
  base_tag: "contact",
  required_config: ["email_subject", "email_body"],
  sub_defs: [
    "page" => [
      "label" => new TranslatableMarkup("Site"),
      "metadata_key" => "contact_form",
      "sub_defs" => [
        "mail" => [
          "label" => new TranslatableMarkup("Message"),
          "required_config" => ["email_subject", "email_body", "email_to"],
        ],
        "copy" => new TranslatableMarkup("Sender copy"),
        "autoreply" => new TranslatableMarkup("Auto-reply"),
      ],
      "variables" => [
        'form' => new TranslatableMarkup("Contact form name"),
        'form_url' => new TranslatableMarkup("Contact form URL"),
      ],
    ],
    "user" => [
      "label" => new TranslatableMarkup("Personal"),
      "sub_defs" => [
        "mail" => new TranslatableMarkup("Message"),
        "copy" => new TranslatableMarkup("Sender copy"),
      ],
      "variables" => [
        'recipient_name' => new TranslatableMarkup("Recipient name"),
        'recipient_edit_url' => new TranslatableMarkup("Recipient edit URL"),
      ],
    ],
  ],
  variables: [
    'contact_message' => new TranslatableMarkup("Contact message entity object"),
    'subject' => new TranslatableMarkup("Subject"),
    'site_name' => new TranslatableMarkup("Site name"),
    'sender_name' => new TranslatableMarkup("Sender name"),
    'sender_url' => new TranslatableMarkup("Sender URL"),
  ],
)]
class ContactMailer extends ComponentMailerBase implements ContactMailerInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function sendMailMessages(MessageInterface $message): bool {
    $personal = $message->isPersonal();
    $tag = $personal ? 'user' : 'page';
    $sender = Address::fromCurrent($message->getSenderMail(), $message->getSenderName());
    $email = $this->newEmail("$tag.mail")
      ->setEntityParam($message)
      ->setParam('sender', $sender);

    if ($personal) {
      $recipient = $message->getPersonalRecipient();
      $email->setParam('recipient', $recipient);
      $email->setTo($recipient);
    }
    else {
      $email->setEntityParam($message->getContactForm());
    }

    $params = $email->getParams();
    $success = $email->send();

    if ($success && $message->copySender()) {
      $success = $this->newEmail("$tag.copy")->setParams($params)->setTo($sender)->send();
    }
    if ($success && !$message->isPersonal()) {
      $success = $this->newEmail("$tag.autoreply")->setParams($params)->setTo($sender)->send();
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    /** @var \Drupal\symfony_mailer\Address $sender */
    $sender = $email->getParam('sender');
    $message = $email->getParam('contact_message');
    $sender_name = $sender->getDisplayName();
    $sender_email = $sender->getEmail();

    if ($account = $sender->getAccount()) {
      $sender_url = User::load($account->id())->toUrl('canonical')->toString();
    }
    else {
      // Clarify that the sender name is not verified; it could potentially
      // clash with a username on this site.
      $sender_name = $this->t('@name (not verified)', ['@name' => $sender_name]);
      $sender_url = $sender_email ? Url::fromUri("mailto:$sender_email") : '';
    }

    $email->setEntityVariable('contact_message')
      ->addLibrary('symfony_mailer/contact')
      ->setVariable('subject', $message->getSubject())
      ->setVariable('site_name', \Drupal::config('system.site')->get('name'))
      ->setVariable('sender_name', $sender_name)
      ->setVariable('sender_url', $sender_url);

    if ($message->isPersonal()) {
      $recipient = $email->getParam('recipient');
      $email->setVariable('recipient_name', $recipient->getDisplayName())
        ->setVariable('recipient_edit_url', $recipient->toUrl('edit-form')->toString());
    }
    else {
      $email->setVariable('form', $email->getParam('contact_form')->label())
        ->setVariable('form_url', Url::fromRoute('<current>')->toString());
    }

    if ($email->getTag(2) == 'mail' && $sender_email) {
      $email->setReplyTo($sender);
    }
  }

}
