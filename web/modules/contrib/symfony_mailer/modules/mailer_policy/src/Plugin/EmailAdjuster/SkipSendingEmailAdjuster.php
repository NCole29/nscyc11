<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;

/**
 * Defines the Skip Sending Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_skip_sending",
  label: new TranslatableMarkup("Skip sending"),
  description: new TranslatableMarkup("Skips the email sending."),
  weight: -1,
)]
class SkipSendingEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    throw new SkipMailException($this->configuration['message']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['message'] = [
      '#type' => 'textfield',
      '#default_value' => $this->configuration['message'] ?? NULL,
      '#description' => $this->t('Users with permission to manage mailer settings will see this message when skipping an email.'),
    ];

    return $form;
  }

}
