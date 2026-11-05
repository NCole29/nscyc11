<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\symfony_mailer\LegacyConverterInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the mail manager service.
 */
class MailerOverrideServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('plugin.manager.mail');
    // Cancel any method calls, for example from mailsystem.
    $definition->setClass('Drupal\mailer_override\MailManagerReplacement')
      ->addArgument(new Reference(OverrideManagerInterface::class))
      ->addArgument(new Reference(LegacyConverterInterface::class))
      ->setMethodCalls([]);
  }

}
