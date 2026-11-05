<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\ReplaceableProcessorInterface;

/**
 * Defines the Plain text alternative Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_plain",
  label: new TranslatableMarkup("Plain text alternative"),
  description: new TranslatableMarkup("Sets the email plain text alternative."),
)]
class PlainEmailAdjuster extends EmailAdjusterBase implements ReplaceableProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $email->setTextBody($this->configuration['value']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textarea',
      '#default_value' => $this->configuration['value'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Plain text alternative.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string {
    return $this->configuration['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(EmailInterface $email, bool &$plain) {
    return $email->getTextBody();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(EmailInterface $email, $value) {
    $email->setTextBody($value);
  }

}
