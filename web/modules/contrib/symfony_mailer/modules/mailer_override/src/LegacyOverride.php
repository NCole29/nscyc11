<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\mailer_override\Plugin\Mailer\LegacyMailerInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;

/**
 * Legacy Override plug-in that uses a message array.
 */
class LegacyOverride implements OverrideInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\mailer_override\Plugin\Mailer\LegacyMailerInterface $mailer
   *   The legacy mailer.
   *
   * @internal
   */
  public function __construct(protected readonly LegacyMailerInterface $mailer) {}

  /**
   * {@inheritdoc}
   */
  public function send(array &$message, EmailProcessorInterface $processor): bool {
    $this->mailer->addProcessor($processor);
    return $this->mailer->send($message);
  }

  /**
   * {@inheritdoc}
   */
  public function import(ImportHelperInterface $helper): void {}

}
