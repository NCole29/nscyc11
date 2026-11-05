<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\MailerOverride;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\mailer_override\Attribute\Override;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\mailer_override\OverrideBase;
use Drupal\mailer_policy\Entity\MailerPolicy;
use Drupal\symfony_mailer\Address;

/**
 * Defines the Override plug-in for commerce order module.
 */
#[Override(
  id: "commerce_order",
  override: ["commerce.order_receipt"],
  config: [
    "core.entity_view_mode.commerce_order.email",
    "core.entity_view_display.commerce_order.default.email",
  ],
  import: new TranslatableMarkup("Order type settings"),
  form_alter: [
    "*" => [
      "remove" => ["emails"],
    ],
  ]
)]
class CommerceOrderOverride extends OverrideBase {

  /**
   * {@inheritdoc}
   */
  protected function fromArray(array $message): bool {
    $order = $message['params']['order'];
    return $this->mailer->sendReceipt($order, !empty($message['params']['resend']));
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {
    foreach (OrderType::loadMultiple() as $id => $order_type) {
      $config = [];
      if ($bcc = $order_type->getReceiptBcc()) {
        $config['email_bcc'] = $helper->policyFromAddresses([new Address($bcc)]);
      }
      if (!$order_type->shouldSendReceipt()) {
        $config['email_skip_sending']['message'] = 'Receipt disabled in settings';
      }
      MailerPolicy::import("commerce_order.receipt..$id", $config);
    }
  }

}
