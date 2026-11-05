<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;

/**
 * Defines the interface for EmailAdjuster plug-ins.
 */
interface EmailAdjusterInterface extends EmailProcessorInterface, ConfigurableInterface {

  /**
   * The maximum length of a summary, beyond which it will be truncated.
   */
  const MAX_SUMMARY = 50;

  /**
   * Generates an adjuster's settings form.
   *
   * @param array $form
   *   A minimally pre-populated form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the (entire) configuration form.
   *
   * @return array
   *   The $form array with additional form elements for the settings of this
   *   filter. The submitted form values should match $this->configuration.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array;

  /**
   * Returns the administrative label for this plugin.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getLabel(): TranslatableMarkup;

  /**
   * Returns a summary for this plugin.
   *
   * @return ?string
   *   The summary, which will be truncated to length self::MAX_SUMMARY.
   */
  public function getSummary(): ?string;

}
