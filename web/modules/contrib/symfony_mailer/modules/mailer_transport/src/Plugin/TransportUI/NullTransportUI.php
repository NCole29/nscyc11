<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Plugin\TransportUI;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_transport\Attribute\TransportUI;

/**
 * Defines the null TransportUI plug-in.
 */
#[TransportUI(
  id: "null",
  label: new TranslatableMarkup("Null"),
  description: new TranslatableMarkup("Disable delivery of messages."),
)]
class NullTransportUI extends TransportUIBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

}
