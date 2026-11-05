<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Unified transport factory to create transports of any type.
 */
interface UnifiedTransportFactoryInterface {

  /**
   * Gets a transport, either creating a new one or reusing an existing one.
   *
   * @param string $dsn
   *   The transport DSN.
   *
   * @return \Symfony\Component\Mailer\Transport\TransportInterface
   *   The transport.
   */
  public function getTransport(string $dsn): TransportInterface;

}
