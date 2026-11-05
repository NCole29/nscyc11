<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines a trait to help writing EmailProcessorInterface implementations.
 */
trait EmailProcessorTrait {

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
  }

  /**
   * {@inheritdoc}
   */
  public function postSend(EmailInterface $email): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    return EmailInterface::DEFAULT_WEIGHT;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return static::class;
  }

}
