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
 * Defines the Body Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_body",
  label: new TranslatableMarkup("Body"),
  description: new TranslatableMarkup("Sets the email body."),
)]
class BodyEmailAdjuster extends EmailAdjusterBase implements ReplaceableProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $content = $this->configuration['content'];
    $body = [
      '#type' => 'processed_text',
      '#text' => $content['value'],
      '#format' => $content['format'] ?? filter_default_format(),
    ];

    if ($existing_body = $email->getBody()) {
      $email->setVariable('body', $existing_body);
    }

    $email->setBody($body);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['content'] = [
      '#title' => $this->t('Content'),
      '#type' => 'text_format',
      '#default_value' => $this->configuration['content']['value'] ?? NULL,
      '#format' => $this->configuration['content']['format'] ?? filter_default_format(),
      '#required' => TRUE,
      '#rows' => 10,
      '#description' => $this->t('Email body.') . $form_state->getValue('mailer_policy_help'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(EmailInterface $email, bool &$plain) {
    $plain = FALSE;
    return $email->getBody()['#text'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(EmailInterface $email, $value) {
    $body = $email->getBody();
    $body['#text'] = $value;
    $email->setBody($body);
  }

}
