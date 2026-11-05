<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\simplenews\SubscriberInterface;

/**
 * Defines the mailer interface for simplenews module subscriber emails.
 */
interface SimplenewsSubscriberMailerInterface extends ComponentMailerInterface {

  /**
   * Sends an email to a subscriber.
   *
   * @param string $operation
   *   The operation: subscribe or validate.
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber.
   *
   * @return bool
   *   Whether successful.
   */
  public function sendToSubscriber(string $operation, SubscriberInterface $subscriber): bool;

}
