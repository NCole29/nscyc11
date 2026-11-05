<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\contact\MessageInterface;

/**
 * Defines the mailer interface for contact module.
 */
interface ContactMailerInterface extends ComponentMailerInterface {

  /**
   * Sends mail messages as appropriate for a given Message form submission.
   *
   * Can potentially send up to three messages as follows:
   * - To the configured recipient;
   * - Auto-reply to the sender; and
   * - Carbon copy to the sender.
   *
   * @param \Drupal\contact\MessageInterface $message
   *   Submitted message entity.
   *
   * @return bool
   *   Whether successful.
   */
  public function sendMailMessages(MessageInterface $message): bool;

}
