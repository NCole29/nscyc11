<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer_test\Transport;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Defines a mail transport that captures sent messages in a key value store.
 *
 * This class is for running tests or for development.
 */
class CaptureTransport implements TransportInterface {

  /**
   * {@inheritdoc}
   */
  public function send(RawMessage $message, ?Envelope $envelope = NULL): ?SentMessage {
    $capturedMails = \Drupal::keyValue('symfony_mailer_test')->get('emails', []);
    $capturedMails[] = $message;
    \Drupal::keyValue('symfony_mailer_test')->set('emails', $capturedMails);
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return 'capture://';
  }

}
