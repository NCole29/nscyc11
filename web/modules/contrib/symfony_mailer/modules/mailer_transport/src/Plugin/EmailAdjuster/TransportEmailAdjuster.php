<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\mailer_transport\Entity\Transport;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Mailer transport Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_transport",
  label: new TranslatableMarkup("Mailer transport"),
  description: new TranslatableMarkup("Sets the mailer transport alternative."),
)]
class TransportEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $email->setTransport($this->configuration['value']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach (Transport::loadMultiple() as $id => $transport) {
      $options[$id] = $transport->label();
    }

    $form['value'] = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->configuration['value'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Transport.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string {
    if ($transport = Transport::load($this->configuration['value'])) {
      return $transport->label();
    }
    return NULL;
  }

}
