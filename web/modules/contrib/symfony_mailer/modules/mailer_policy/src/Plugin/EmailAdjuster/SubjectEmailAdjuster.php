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
 * Defines the Subject header Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_subject",
  label: new TranslatableMarkup("Subject"),
  description: new TranslatableMarkup("Sets the email subject."),
)]
class SubjectEmailAdjuster extends EmailAdjusterBase implements ReplaceableProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $email->setSubject($this->configuration['value']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['value'] = [
      '#type' => 'textfield',
      '#default_value' => $this->configuration['value'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Email subject.') . $form_state->getValue('mailer_policy_help'),
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
    return $email->getSubject();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(EmailInterface $email, $value) {
    $email->setSubject($value);
  }

}
