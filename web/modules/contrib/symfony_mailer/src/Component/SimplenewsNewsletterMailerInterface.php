<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\simplenews\SubscriberInterface;

/**
 * Defines the mailer interface for simplenews module newsletter emails.
 */
interface SimplenewsNewsletterMailerInterface extends ComponentMailerInterface {

  /**
   * Sends a newsletter issue email.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $issue
   *   The newsletter issue to send.
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber.
   * @param bool|string $mode
   *   The mode of sending: test, extra or node.
   */
  public function sendIssue(ContentEntityInterface $issue, SubscriberInterface $subscriber, string $mode): bool;

}
