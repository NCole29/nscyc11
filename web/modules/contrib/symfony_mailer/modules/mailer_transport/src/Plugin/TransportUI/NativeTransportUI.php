<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Plugin\TransportUI;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_transport\Attribute\TransportUI;

/**
 * Defines the native TransportUI plug-in.
 */
#[TransportUI(
  id: "native",
  label: new TranslatableMarkup("Native"),
  description: new TranslatableMarkup("Use the sendmail binary and options configured in the sendmail_path setting of php.ini."),
  warning: new TranslatableMarkup("<b>Not recommended</b>, prefer Sendmail. If php.ini uses the sendmail -t command, you won't have error reporting and Bcc headers won't be removed."),
)]
class NativeTransportUI extends TransportUIBase {

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
