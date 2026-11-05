<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\MailerOverride;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\contact\Entity\ContactForm;
use Drupal\mailer_override\Attribute\Override;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\mailer_override\OverrideBase;
use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Defines the Override plug-in for contact module page forms.
 */
#[Override(
  id: "contact",
  import: new TranslatableMarkup("Contact form recipients"),
  form_alter: [
    "*" => [
      "remove" => ["recipients", "reply"],
      "default" => ["recipients" => "[site:mail]"],
      "tag" => "contact.page",
    ],
  ]
)]
class ContactOverride extends OverrideBase {

  /**
   * {@inheritdoc}
   */
  protected function fromArray(array $message): bool {
    if (substr($message['key'], 5) == 'mail') {
      return $this->mailer->sendMailMessages($message['params']['contact_message']);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {
    foreach (ContactForm::loadMultiple() as $id => $form) {
      if ($id != 'personal') {
        $addresses = $helper->parseAddress(implode(',', $form->getRecipients()));
        $config = [
          'email_to' => $helper->policyFromAddresses($addresses),
          'email_body' => $helper->policyFromPlainBody($form->getMessage()),
        ];
        MailerPolicy::import("contact.page.mail..$id", $config);

        if ($reply = $form->getReply()) {
          $config = [
            'email_body' => $helper->policyFromPlainBody($reply),
          ];
          MailerPolicy::import("contact.page.autoreply..$id", $config);
        }
      }
    }
  }

}
