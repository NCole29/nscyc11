<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;

/**
 * Defines the base class for EmailAdjuster plug-ins.
 */
abstract class EmailAdjusterBase extends PluginBase implements EmailAdjusterInterface {

  use EmailProcessorTrait;

  /**
   * {@inheritdoc}
   */
  const DEFAULT_WEIGHT = 400;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): TranslatableMarkup {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    $weight = $this->getPluginDefinition()['weight'] ?? static::DEFAULT_WEIGHT;
    return is_array($weight) ? $weight[$phase] : $weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

}
