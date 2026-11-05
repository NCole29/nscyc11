<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

/**
 * Defines the mailer interface for verification emails.
 */
interface VerifyMailerInterface extends ComponentMailerInterface {

  /**
   * Sends a verification email.
   *
   * @param mixed $to
   *   The to addresses, see Address::convert().
   *
   * @return bool
   *   Whether successful.
   */
  public function verify($to): bool;

}
