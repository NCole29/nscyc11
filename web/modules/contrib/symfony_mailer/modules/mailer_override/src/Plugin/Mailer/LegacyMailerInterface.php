<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\Mailer;

/**
 * Defines the mailer interface for legacy emails.
 */
interface LegacyMailerInterface {

  /**
   * Sends a message.
   *
   * @param array $message
   *   Legacy message array.
   *
   * @return bool
   *   Whether successful.
   */
  public function send(array $message): bool;

}
