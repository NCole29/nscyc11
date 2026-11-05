<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Entity;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\mailer_policy\AdjusterPluginCollection;

/**
 * Defines a Mailer Policy configuration entity class.
 */
interface MailerPolicyInterface extends ConfigEntityInterface {

  /**
   * Gets the email tag this policy applies to.
   *
   * @return string
   *   Email tag, or empty if the policy applies to all tags.
   */
  public function getTag(): string;

  /**
   * Gets the config entity this policy applies to.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   Entity, or NULL if the policy applies to all entities.
   */
  public function getEntity(): ?ConfigEntityInterface;

  /**
   * Gets a human-readable label for the email tag this policy applies to.
   *
   * @param int $skip
   *   Number of levels to skip when displaying the tag.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   Email tag label.
   */
  public function getTagLabel(int $skip = 0): MarkupInterface;

  /**
   * Gets a human-readable label for the config entity this policy applies to.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Email config entity label. This can be empty if the Mailer doesn't
   *   support entities or if the entity has no label.
   */
  public function getEntityLabel(): string|MarkupInterface;

  /**
   * Sets the email adjuster configuration for this policy record.
   *
   * @param array $configuration
   *   An associative array of adjuster configuration, keyed by the plug-in ID
   *   with value as an array of configured settings.
   *
   * @return $this
   */
  public function setConfiguration(array $configuration): self;

  /**
   * Gets the email adjuster configuration for this policy record.
   *
   * @return array
   *   An associative array of adjuster configuration, keyed by the plug-in ID
   *   with value as an array of configured settings.
   */
  public function getConfiguration(): array;

  /**
   * Gets the mailer definition for this policy record.
   *
   * @return array
   *   An associative array of the mailer plug-in definition information.
   */
  public function getMailerDefinition(): array;

  /**
   * Returns the ordered collection of configured adjuster plugin instances.
   *
   * @return \Drupal\symfony_mailer\Processor\AdjusterPluginCollection
   *   The adjuster collection.
   */
  public function adjusters(): AdjusterPluginCollection;

  /**
   * Returns all available adjuster plugin definitions.
   *
   * @return array
   *   An associative array of plugin definitions, keyed by the plug-in ID.
   */
  public function adjusterDefinitions(): array;

  /**
   * Gets a short human-readable summary of the configured policy.
   *
   * @param bool $expanded
   *   (Optional) If FALSE return just the labels. If TRUE include a short
   *   summary of each element.
   *
   * @return string
   *   Summary text.
   */
  public function getSummary($expanded = FALSE): string;

  /**
   * Helper callback to sort entities.
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b): int;

  /**
   * Helper callback to sort entities with the most specific policy first.
   *
   * The default sort puts the least specific first.
   */
  public static function sortSpecific(ConfigEntityInterface $a, ConfigEntityInterface $b): int;

  /**
   * Loads a Mailer Policy, or creates a new one.
   *
   * @param string $id
   *   The id of the policy to load or create.
   *
   * @return static
   *   The policy object.
   */
  public static function loadOrCreate(string $id): static;

  /**
   * Loads config for a Mailer Policy including inherited policy.
   *
   * @param string $id
   *   The id of the policy.
   * @param bool $includeSelf
   *   - TRUE (default) to include the policy itself.
   *   - FALSE to include only inherited policy.
   *
   * @return array
   *   The configuration array.
   */
  public static function loadInheritedConfig(string $id, bool $includeSelf = TRUE): array;

  /**
   * Imports a Mailer Policy from configuration.
   *
   * @param string $id
   *   The id of the policy to import.
   * @param array $configuration
   *   An associative array of adjuster configuration, keyed by the plug-in ID
   *   with value as an array of configured settings.
   */
  public static function import(string $id, array $configuration): void;

}
