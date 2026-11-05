<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor\automatic;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;

/**
 * Defines the Defaults Email Processor.
 */
class DefaultsEmailProcessor implements EmailProcessorInterface {

  use EmailProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $theme = $email->getTheme();
    $email->setSender('<site>')
      ->addTextHeader('X-Mailer', 'Drupal')
      ->addLibrary("$theme/email");
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    return 100;
  }

}
