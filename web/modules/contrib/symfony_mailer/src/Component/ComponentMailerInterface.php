<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;

/**
 * Defines the interface for Mailer plugins.
 */
interface ComponentMailerInterface extends EmailProcessorInterface {

  /**
   * Adds an email processor to the next email that is sent.
   *
   * @param \Drupal\symfony_mailer\Processor\EmailProcessorInterface $processor
   *   The email processor.
   *
   * @return $this
   */
  public function addProcessor(EmailProcessorInterface $processor): static;

  /**
   * Adds a callback function to the next email that is sent.
   *
   * @param callable $function
   *   The function to call.
   * @param int $phase
   *   (Optional) The phase to run in, one of the EmailInterface::PHASE_
   *   constants.
   * @param int $weight
   *   (Optional) The weight, lower values run earlier.
   * @param string $id
   *   (Optional) A unique ID.
   *
   * @return $this
   */
  public function addCallback(callable $function, int $phase = EmailInterface::PHASE_BUILD, int $weight = EmailInterface::DEFAULT_WEIGHT, ?string $id = NULL): static;

}
