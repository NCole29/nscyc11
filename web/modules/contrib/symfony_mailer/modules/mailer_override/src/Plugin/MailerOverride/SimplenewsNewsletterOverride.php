<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\MailerOverride;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_override\Attribute\Override;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\mailer_override\OverrideBase;
use Drupal\mailer_policy\Entity\MailerPolicy;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\symfony_mailer\Address;

/**
 * Defines the Override plug-in for simplenews module newsletter emails.
 */
#[Override(
  id: "simplenews.newsletter",
  override: ["simplenews.node", "simplenews.test", "simplenews.extra"],
  import: new TranslatableMarkup("Simplenews newsletter settings"),
  form_alter: [
    "*" => [
      "remove" => [
        "email",
        "simplenews_sender_information",
        "simplenews_subject",
      ],
    ],
  ],
)]
class SimplenewsNewsletterOverride extends OverrideBase {

  /**
   * {@inheritdoc}
   */
  protected function fromArray(array $message):  bool {
    $mail = $message['params']['simplenews_mail'];
    return $this->mailer->sendIssue($mail->getIssue(), $mail->getSubscriber(), $mail->getKey());
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {
    $settings = $helper->config()->get('simplenews.settings');
    $from = new Address($settings->get('newsletter.from_address'), $settings->get('newsletter.from_name'));
    $config['email_from'] = $helper->policyFromAddresses([$from]);
    MailerPolicy::import('simplenews.newsletter.node', $config);

    foreach (Newsletter::loadMultiple() as $id => $newsletter) {
      $from = new Address($newsletter->from_address, $newsletter->from_name);
      $config['email_from'] = $helper->policyFromAddresses([$from]);
      $config['email_subject']['value'] = $newsletter->subject;
      MailerPolicy::import("simplenews.newsletter.node..$id", $config);
    }
  }

}
