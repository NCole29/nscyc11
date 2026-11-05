<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the interface for Email Processors.
 */
interface EmailProcessorInterface {

  /**
   * Process emails during the initialise phase.
   *
   * Set processors, parameters, theme and destination addresses.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to initialize.
   */
  public function init(EmailInterface $email): void;

  /**
   * Process emails during the build phase.
   *
   * Construct the email. The language, theme, and account are now correct. The
   * body is not yet rendered and stored as a Drupal render array.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function build(EmailInterface $email): void;

  /**
   * Process emails during the post-render phase.
   *
   * Act on the rendered HTML, or any header.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function postRender(EmailInterface $email): void;

  /**
   * Process emails during the post-send phase.
   *
   * No further alterations allowed.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to process.
   */
  public function postSend(EmailInterface $email): void;

  /**
   * Gets the weight of the email processor.
   *
   * @param int $phase
   *   The phase that will run, one of the EmailInterface::PHASE_ constants.
   *
   * @return int
   *   The weight.
   */
  public function getWeight(int $phase): int;

  /**
   * Gets the ID of the email processor.
   *
   * @return string
   *   The ID.
   */
  public function getId(): string;

}
