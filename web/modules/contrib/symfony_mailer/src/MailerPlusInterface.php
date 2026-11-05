<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\symfony_mailer\Processor\EmailProcessorInterface;

/**
 * Interface for enhanced mailer service.
 */
interface MailerPlusInterface {

  /**
   * Creates a new email.
   *
   * @param string $tag
   *   Tag used to identify the type or source of this email.
   *   @see \Drupal\symfony_mailer\EmailInterface::getTag()
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   The new email.
   */
  public function newEmail(string $tag): EmailInterface;

  /**
   * Sends an email.
   *
   * @param \Drupal\symfony_mailer\InternalEmailInterface $email
   *   The email to send.
   *
   * @return bool
   *   Whether successful.
   */
  public function send(InternalEmailInterface $email): bool;

  /**
   * Adds an email processor to all emails that are sent.
   *
   * @param \Drupal\symfony_mailer\Processor\EmailProcessorInterface $processor
   *   The email processor.
   *
   * @return $this
   */
  public function addProcessor(EmailProcessorInterface $processor): static;

}
