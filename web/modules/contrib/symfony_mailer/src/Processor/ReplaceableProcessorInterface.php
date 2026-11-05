<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Interface for Email Processors that support token/variable replacement.
 */
interface ReplaceableProcessorInterface extends EmailProcessorInterface {

  /**
   * Gets the value of the parameter that supports replacement.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   * @param bool &$plain
   *   TRUE (default) if the value is plain text.
   *   FALSE if the value is markup.
   *
   * @return mixed
   *   The value.
   */
  public function getValue(EmailInterface $email, bool &$plain);

  /**
   * Sets the value of the parameter that supports replacement.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   * @param mixed $value
   *   The value.
   */
  public function setValue(EmailInterface $email, $value);

}
