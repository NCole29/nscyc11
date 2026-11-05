<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * TransportUI plugin manager.
 */
class TransportUIManager extends DefaultPluginManager implements TransportUIManagerInterface {

  /**
   * TransportUIManager constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   *
   * @internal
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/TransportUI', $namespaces, $module_handler, 'Drupal\mailer_transport\TransportUIInterface', 'Drupal\mailer_transport\Attribute\TransportUI');
    $this->setCacheBackend($cache_backend, 'mailer_transport_definitions');
    $this->alterInfo('mailer_transport_info');
  }

}
