<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Plugin\TransportUI;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_transport\Attribute\TransportUI;

/**
 * Defines the sendmail TransportUI plug-in.
 */
#[TransportUI(
  id: "sendmail",
  label: new TranslatableMarkup("Sendmail"),
  description: new TranslatableMarkup("Use the local sendmail binary to send emails."),
)]
class SendmailTransportUI extends TransportUIBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'query' => ['command' => ''],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $commands = Settings::get('mailer_sendmail_commands', []);
    $commands = ['' => $this->t('&lt;Default&gt;')] + array_combine($commands, $commands);

    $form['command'] = [
      '#type' => 'radios',
      '#title' => $this->t('Command'),
      '#default_value' => $this->configuration['query']['command'],
      '#description' => $this->t('Sendmail command to execute. Configure available commands by setting the variable %var in %file.', [
        '%var' => 'mailer_sendmail_commands',
        '%file' => 'settings.php',
      ]),
      '#options' => $commands,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['query']['command'] = $form_state->getValue('command');
  }

}
