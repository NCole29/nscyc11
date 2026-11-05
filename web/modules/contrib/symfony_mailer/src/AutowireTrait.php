<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;

/**
 * Defines a trait for automatically wiring dependencies from the container.
 *
 * Extension of \Drupal\Core\DependencyInjection\AutowireTrait.
 * - Add caching to reduce performance issues.
 * - Allow passing extra arguments for the constructor.
 * - Allow optional dependencies.
 *
 * @internal
 */
trait AutowireTrait {

  /**
   * Array of service names to use when calling the constructor.
   */
  protected static array $autowireServices = [];

  /**
   * Instantiates a new instance of the implementing class using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param mixed ...$args
   *   Extra arguments for the constructor.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, ...$args): static {
    $params = [];
    if (!static::$autowireServices) {
      static::getServices($container, static::class);
    }

    foreach (static::$autowireServices as $service) {
      $params[] = $service ? $container->get($service) : NULL;
    }
    return new static(...$params, ...$args);
  }

  /**
   * Gets the services to use for the constructor of the specified class.
   *
   * The services are added to the static array.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param string $class
   *   The class to construct.
   */
  protected static function getServices(ContainerInterface $container, string $class) {
    $constructor = new \ReflectionMethod($class, '__construct');
    foreach ($constructor->getParameters() as $parameter) {
      if ($parameter->isVariadic()) {
        $declaring_class = $constructor->getDeclaringClass()->getName();
        static::getServices($container, get_parent_class($declaring_class));
        break;
      }
      $service = ltrim((string) $parameter->getType(), '?');
      foreach ($parameter->getAttributes(Autowire::class) as $attribute) {
        $service = (string) $attribute->newInstance()->value;
      }

      $type = $parameter->getType();
      if (!($type instanceof \ReflectionNamedType) || $type->isBuiltin()) {
        // We have finished autowiring.
        break;
      }

      if ($container->has($service)) {
        static::$autowireServices[] = $service;
      }
      elseif ($parameter->isOptional()) {
        static::$autowireServices[] = NULL;
      }
      else {
        throw new AutowiringFailedException($service, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s::_construct()", you should configure its value explicitly.', $service, $parameter->getName(), static::class));
      }
    }
  }

}
