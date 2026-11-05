<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * TransportUI plugin interface.
 */
interface TransportUIInterface extends ConfigurableInterface, PluginInspectionInterface, PluginFormInterface {

  /**
   * Gets the DSN.
   *
   * @return string
   *   The DSN.
   */
  public function getDsn(): string;

}
