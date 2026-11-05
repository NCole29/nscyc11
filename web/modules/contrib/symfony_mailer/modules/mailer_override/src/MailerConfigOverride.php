<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mailer Override configuration override.
 */
class MailerConfigOverride implements ConfigFactoryOverrideInterface {

  /**
   * Whether cache has been built.
   */
  protected bool $builtCache = FALSE;

  /**
   * Array of config overrides.
   *
   * As required by ConfigFactoryOverrideInterface::loadOverrides().
   */
  protected array $configOverrides = [];

  /**
   * Constructs the MailerConfigOverride object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected readonly ContainerInterface $container,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names): array {
    $this->buildCache();
    return array_intersect_key($this->configOverrides, array_flip($names));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'MailerOverride';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name): CacheableMetadata {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

  /**
   * Build cache of config overrides.
   */
  protected function buildCache(): void {
    // The calculation of config overrides depends on configuration settings,
    // so we have to be careful to avoid a circular dependency.
    // - Wait for key services we depend on to load, as they may read config.
    // - Avoid dependency injection, instead load services when we use them.
    // - Mark the cache as built before processing to ensure it only runs once.
    if (!$this->builtCache && $this->moduleHandler->isLoaded() && $this->container->initialized(EntityTypeManagerInterface::class)) {
      $this->builtCache = TRUE;
      $overrideManager = \Drupal::service(OverrideManagerInterface::class);
      foreach ($overrideManager->getDefinitions() as $definition) {
        $this->configOverrides = array_merge($this->configOverrides, $definition['config_overrides']);
      }
    }
  }

}
