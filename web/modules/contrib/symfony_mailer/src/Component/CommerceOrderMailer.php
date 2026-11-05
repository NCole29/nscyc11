<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Mailer plug-in for commerce order module.
 *
 * The template variable 'body' is generated from the order type settings for
 * "Manage Display" (1). The "Order item table" formatter is generated from the
 * "Order items" view (2).
 * (1) /admin/commerce/config/order-types/XXX/edit/display/email
 * (2) /admin/structure/views/view/commerce_order_item_table.
 *
 * Replaces:
 * - \Drupal\commerce_order\Mail\OrderReceiptMail
 * - \Drupal\commerce\MailHandler
 */
#[MailerInfo(
  base_tag: "commerce_order",
  label: new TranslatableMarkup("Commerce order"),
  sub_defs: [
    "receipt" => new TranslatableMarkup("Receipt"),
    "resend_receipt" => new TranslatableMarkup("Resend receipt"),
  ],
  metadata_key: "commerce_order_type",
  required_config: ["email_subject", "email_body"],
  variables: [
    'commerce_order' => new TranslatableMarkup("Order entity object"),
    'order_number' => new TranslatableMarkup("Order number"),
    'store' => new TranslatableMarkup("Store name"),
  ],
)]
class CommerceOrderMailer extends ComponentMailerBase implements CommerceOrderMailerInterface {

  /**
   * {@inheritdoc}
   */
  public function sendReceipt(OrderInterface $order, bool $resend = FALSE): bool {
    $sub_type = $resend ? 'resend_receipt' : 'receipt';
    $customer = $order->getCustomer();
    $to = $customer->isAuthenticated() ? $customer : $order->getEmail();
    return $this->newEmail($sub_type)
      ->setEntityParam(OrderType::load($order->bundle()))
      ->setEntityParam($order)
      ->setTo($to)
      ->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $order = $email->getParam('commerce_order');
    $store = $order->getStore();

    $email->setEntityVariable('commerce_order')
      ->addLibrary('symfony_mailer/commerce_order')
      ->setVariable('order_number', $order->getOrderNumber())
      ->setVariable('store', $store->getName());

    // Get the actual email value without picking up the default from the site
    // mail. Instead we prefer to default from Mailer policy.
    if ($store_email = $store->get('mail')->value) {
      $email->setFrom($store_email);
    }
  }

}
