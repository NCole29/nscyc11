<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\mailer_policy\Entity\MailerPolicy;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;

/**
 * Provides the email adjuster plugin manager.
 */
class EmailAdjusterManager extends DefaultPluginManager implements EmailAdjusterManagerInterface, EmailProcessorInterface {

  use EmailProcessorTrait;

  /**
   * Constructs the EmailAdjusterManager object.
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
    parent::__construct('Plugin/EmailAdjuster', $namespaces, $module_handler, 'Drupal\mailer_policy\EmailAdjusterInterface', 'Drupal\mailer_policy\Attribute\EmailAdjuster');
    $this->setCacheBackend($cache_backend, 'mailer_policy_definitions');
    $this->alterInfo('mailer_adjuster_info');
  }

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    // Force creating the policy so we can get the mailer definition.
    $id = $email->getTag();
    $mailer_definition = MailerPolicy::loadOrCreate($id)->getMailerDefinition();

    // Find the entity in the params and add it to the ID.
    $meta_key = $mailer_definition['metadata_key'];
    if ($meta_key && $entity = $email->getParam($meta_key)) {
      $id .= '..' . $entity->id();
    }
    $email->setParam('mailer_policy.id', $id);
    $policy_config = MailerPolicy::loadInheritedConfig($id);

    // Check for required config else abort.
    $missing_config = array_diff($mailer_definition['required_config'], array_keys($policy_config));
    if ($missing_config) {
      throw new SkipMailException($mailer_definition['label'] . ' not configured');
    }

    // Add adjusters.
    foreach ($policy_config as $plugin_id => $config) {
      if ($this->hasDefinition($plugin_id)) {
        $email->addProcessor($this->createInstance($plugin_id, $config));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $policy_config = MailerPolicy::loadInheritedConfig($email->getParam('mailer_policy.id'));

    // Update the configuration, which may include translated values.
    foreach ($email->getProcessors() as $processor) {
      if ($processor instanceof EmailAdjusterInterface) {
        $plugin_id = $processor->getId();
        $processor->setConfiguration($policy_config[$plugin_id]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    return 0;
  }

}
