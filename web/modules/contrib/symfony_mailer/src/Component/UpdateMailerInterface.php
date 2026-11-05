<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

/**
 * Defines the mailer interface for update module.
 */
interface UpdateMailerInterface extends ComponentMailerInterface {

  /**
   * Sends an update notification message.
   *
   * @return bool
   *   Whether successful.
   */
  public function notify(): bool;

}
