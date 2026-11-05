<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Register auto-configuration of Mailer plugins.
 */
class SymfonyMailerServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new MailerPass());
  }

}
