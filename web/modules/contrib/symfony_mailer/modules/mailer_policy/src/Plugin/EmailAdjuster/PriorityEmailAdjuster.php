<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\EmailInterface;
use Symfony\Component\Mime\Email;

/**
 * Defines the Priority header Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_priority",
  label: new TranslatableMarkup("Priority"),
  description: new TranslatableMarkup("Sets the email priority."),
)]
class PriorityEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $priority = $this->configuration['value'];
    $email->setPriority($priority);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'select',
      '#options' => $this->getPriorities(),
      '#default_value' => $this->configuration['value'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Email priority.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string {
    return $this->getPriorities()[$this->configuration['value']];
  }

  /**
   * Returns a list of priority options.
   *
   * @return string[]
   *   The priority options.
   */
  protected function getPriorities(): array {
    return [
      Email::PRIORITY_HIGHEST => $this->t('Highest'),
      Email::PRIORITY_HIGH => $this->t('High'),
      Email::PRIORITY_NORMAL => $this->t('Normal'),
      Email::PRIORITY_LOW => $this->t('Low'),
      Email::PRIORITY_LOWEST => $this->t('Lowest'),
    ];
  }

}
