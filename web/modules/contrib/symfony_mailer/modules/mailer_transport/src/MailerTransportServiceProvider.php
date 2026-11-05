<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\Mailer\Transport;

/**
 * Dynamically create transport factory services.
 */
class MailerTransportServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    foreach (Transport::getDefaultFactories() as $factory) {
      $class = get_class($factory);
      if (!$container->hasDefinition($class)) {
        $definition = (new ChildDefinition('Symfony\Component\Mailer\Transport\AbstractTransportFactory'))
          ->setClass($class)
          ->addTag('mailer.transport_factory');
        $container->setDefinition($class, $definition);
      }
    }
  }

}
