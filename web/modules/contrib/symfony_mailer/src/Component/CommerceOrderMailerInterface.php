<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines the mailer interface for commerce order module.
 */
interface CommerceOrderMailerInterface extends ComponentMailerInterface {

  /**
   * Sends the order receipt email.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param bool $resend
   *   Whether the receipt mail is being resent by an administrator.
   *
   * @return bool
   *   Whether successful.
   */
  public function sendReceipt(OrderInterface $order, bool $resend = FALSE): bool;

}
