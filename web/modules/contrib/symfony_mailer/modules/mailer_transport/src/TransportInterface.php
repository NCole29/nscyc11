<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the Mailer Transport configuration interface.
 */
interface TransportInterface extends ConfigEntityInterface {

  /**
   * Returns the transport plugin.
   *
   * @return \Drupal\mailer_transport\TransportUIInterface
   *   The transport plugin used by this mailer transport entity.
   */
  public function getPlugin(): TransportUIInterface;

  /**
   * Returns the transport plugin ID.
   *
   * @return string
   *   The transport plugin ID.
   */
  public function getPluginId(): string;

  /**
   * Sets the transport plugin.
   *
   * @param string $plugin_id
   *   The transport plugin ID.
   *
   * @return $this
   */
  public function setPluginId($plugin_id): self;

  /**
   * Gets the DSN.
   *
   * @return string
   *   The DSN.
   */
  public function getDsn(): string;

  /**
   * Sets this as the default transport.
   *
   * @return $this
   */
  public function setAsDefault(): self;

  /**
   * Determines if this is the default transport.
   *
   * @return bool
   *   TRUE if this is the default transport, FALSE otherwise.
   */
  public function isDefault(): bool;

}
