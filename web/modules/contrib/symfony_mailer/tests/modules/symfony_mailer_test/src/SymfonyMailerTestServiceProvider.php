<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\symfony_mailer_test\Transport\CaptureTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Enables the capture mailer transport.
 */
class SymfonyMailerTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $definition = $container->getDefinition(TransportInterface::class);
    $definition->setClass(CaptureTransport::class);
  }

}
