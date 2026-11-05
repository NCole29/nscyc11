<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Drupal\Core\Site\Settings;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Command validation decorator for sendmail transport factory.
 *
 * Copied from Core issue [#3379794].
 *
 * @see https://www.drupal.org/project/drupal/issues/3379794
 */
class SendmailCommandValidationTransportFactory implements TransportFactoryInterface {

  /**
   * Construct command validation decorator for sendmail transport factory.
   *
   * @param \Symfony\Component\Mailer\Transport\TransportFactoryInterface $inner
   *   The decorated sendmail transport factory.
   */
  public function __construct(protected TransportFactoryInterface $inner) {
  }

  /**
   * {@inheritdoc}
   */
  public function create(Dsn $dsn): TransportInterface {
    $command = $dsn->getOption('command');
    if (!empty($command)) {
      $commands = Settings::get('mailer_sendmail_commands', []);
      if (!in_array($command, $commands, TRUE)) {
        throw new \RuntimeException("Unsafe sendmail command {$command}");
      }
    }

    return $this->inner->create($dsn);
  }

  /**
   * {@inheritdoc}
   */
  public function supports(Dsn $dsn): bool {
    return $this->inner->supports($dsn);
  }

}
