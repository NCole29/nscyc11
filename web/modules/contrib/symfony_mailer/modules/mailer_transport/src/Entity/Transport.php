<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_transport\TransportInterface;
use Drupal\mailer_transport\TransportUIInterface;
use Drupal\mailer_transport\TransportUIManagerInterface;

/**
 * Defines a Mailer Transport configuration entity class.
 */
#[ConfigEntityType(
  id: "mailer_transport",
  label: new TranslatableMarkup("Mailer Transport"),
  handlers: [
    "list_builder" => "Drupal\mailer_transport\TransportListBuilder",
    "form" => [
      "edit" => "Drupal\mailer_transport\Form\TransportForm",
      "add" => "Drupal\mailer_transport\Form\TransportAddForm",
      "delete" => "Drupal\Core\Entity\EntityDeleteForm",
    ],
  ],
  admin_permission: "administer mailer",
  entity_keys: [
    "id" => "id",
    "label" => "label",
  ],
  links: [
    "edit-form" => "/admin/config/system/mailer/transport/{mailer_transport}",
    "delete-form" => "/admin/config/system/mailer/transport/{mailer_transport}/delete",
    "set-default" => "/admin/config/system/mailer/transport/{mailer_transport}/set-default",
    "collection" => "/admin/config/system/mailer/transport",
  ],
  config_export: [
    "id",
    "label",
    "plugin",
    "configuration",
  ]
)]
class Transport extends ConfigEntityBase implements TransportInterface, EntityWithPluginCollectionInterface {

  /**
   * The unique ID of the transport.
   */
  protected string $id;

  /**
   * The label of the transport.
   */
  protected string $label;

  /**
   * The plugin instance ID.
   */
  protected string $plugin;

  /**
   * The plugin instance configuration.
   */
  protected array $configuration = [];

  /**
   * The plugin collection that holds the plugin for this entity.
   */
  protected DefaultSingleLazyPluginCollection $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): TransportUIInterface {
    return $this->getPluginCollection()->get($this->plugin);
  }

  /**
   * Encapsulates the creation of the block's LazyPluginCollection.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   *   The block's plugin collection.
   */
  protected function getPluginCollection(): DefaultSingleLazyPluginCollection {
    if (!isset($this->pluginCollection)) {
      $this->pluginCollection = new DefaultSingleLazyPluginCollection(\Drupal::service(TransportUIManagerInterface::class), $this->plugin, $this->configuration);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'configuration' => $this->getPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId(): string {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setPluginId($plugin): static {
    $this->plugin = $plugin;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDsn(): string {
    return $this->getPlugin()->getDsn();
  }

  /**
   * {@inheritdoc}
   */
  public function setAsDefault(): static {
    \Drupal::configFactory()->getEditable('mailer_transport.settings')->set('default_transport', $this->id())->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault(): bool {
    // Get the default transport without overrides.
    return \Drupal::config('mailer_transport.settings')->getOriginal('default_transport', FALSE) == $this->id();
  }

  /**
   * Gets the default transport.
   *
   * @return ?\Drupal\mailer_transport\TransportInterface
   *   The default transport.
   */
  public static function loadDefault(): ?TransportInterface {
    $id = \Drupal::config('mailer_transport.settings')->get('default_transport');
    return $id ? static::load($id) : NULL;
  }

}
