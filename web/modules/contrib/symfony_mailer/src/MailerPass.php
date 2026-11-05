<?php

namespace Drupal\symfony_mailer;

use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Discover tagged mailer services.
 */
class MailerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $mailer_services = [];
    foreach ($container->getDefinitions() as $service_id => $service_definition) {
      $class = $service_definition->getClass();
      if ($class && class_exists($class) && isset(class_implements($class)[ComponentMailerInterface::class])) {
        $reflection_class = $container->getReflectionClass($class);

        if ($attributes = $reflection_class->getAttributes(MailerInfo::class)) {
          /** @var \Drupal\Component\Plugin\Attribute\AttributeInterface $attribute */
          $attribute = $attributes[0]->newInstance();
          $attribute->setClass($class);
          $definition = $attribute->get();
          $definition['service_id'] = $service_id;
          $mailer_services[$service_id] = $definition;
          $service_definition->setArgument('$baseTag', $definition['base_tag']);
        }

      }
    }
    $container->setParameter('symfony_mailer.mailers', $mailer_services);
  }

}
