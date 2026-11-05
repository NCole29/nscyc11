<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\LegacyConverterInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;

/**
 * EmailProcessor for legacy emails.
 */
class LegacyProcessor implements EmailProcessorInterface {

  use EmailProcessorTrait;

  /**
   * LegacyProcessor constructor.
   *
   * @param \Drupal\symfony_mailer\LegacyConverterInterface $legacyConverter
   *   The legacy converter.
   * @param array $message
   *   The legacy message array.
   *
   * @internal
   */
  public function __construct(protected readonly LegacyConverterInterface $legacyConverter, protected array &$message) {}

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    if (empty($this->message['send'])) {
      throw new SkipMailException('MailManagerInterface call with $send = FALSE');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSend(EmailInterface $email): void {
    $this->message['result'] = !$email->getError();
    $this->legacyConverter->emailToArray($email, $this->message);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    return 1000;
  }

}
