<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\user\UserInterface;

/**
 * Defines the mailer interface for user module.
 */
interface UserMailerInterface extends ComponentMailerInterface {

  /**
   * Sends a user notification message.
   *
   * @param string $op
   *   The operation being performed on the account.
   * @param \Drupal\user\UserInterface $user
   *   The user to notify.
   *
   * @return bool
   *   Whether successful.
   */
  public function notify(string $op, UserInterface $user): bool;

}
