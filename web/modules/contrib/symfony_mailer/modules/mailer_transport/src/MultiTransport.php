<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Drupal\mailer_transport\Entity\Transport;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport as TransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Multi-transport which distributes emails amongst a collection of transports.
 */
class MultiTransport implements TransportInterface, UnifiedTransportFactoryInterface {

  /**
   * The transport factory.
   */
  protected readonly TransportFactory $transportFactory;

  /**
   * Array of created transports, keyed by their ID.
   *
   * @var array<string, TransportInterface>
   */
  protected array $transports = [];

  /**
   * Constructs a new multi-transport.
   *
   * @param Iterable<TransportFactoryInterface> $factories
   *   A list of transport factories.
   *
   * @internal
   */
  public function __construct(
    #[AutowireIterator(tag: 'mailer.transport_factory')]
    iterable $factories,
  ) {
    $this->transportFactory = new TransportFactory($factories);
  }

  /**
   * {@inheritdoc}
   */
  public function send(RawMessage $message, ?Envelope $envelope = NULL): ?SentMessage {
    $key = $message->getHeaders()->getHeaderBody('X-Transport') ?? '';
    if ($key) {
      $message->getHeaders()->remove('X-Transport');
    }

    if (strpos($key, ':') === FALSE) {
      // Lookup transport ID.
      $key = $this->getTransportDsn($key);
    }

    return $this->getTransport($key)->send($message, $envelope);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransport(string $dsn): TransportInterface {
    if (empty($this->transports[$dsn])) {
      $this->transports[$dsn] = $this->transportFactory->fromString($dsn);
    }

    return $this->transports[$dsn];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return static::class;
  }

  /**
   * Get the transport DSN for a transport ID.
   *
   * @param string $transport_id
   *   The transport ID, or an empty string for the default transport.
   *
   * @return string
   *   The DSN.
   */
  protected function getTransportDsn(string $transport_id): string {
    $transport_config = $transport_id ? Transport::load($transport_id) : Transport::loadDefault();
    if (!$transport_config) {
      $msg = $transport_id ? "Missing transport $transport_id." : "Missing default transport.";
      throw new MissingTransportException($msg);
    }
    return $transport_config->getDsn();
  }

}
