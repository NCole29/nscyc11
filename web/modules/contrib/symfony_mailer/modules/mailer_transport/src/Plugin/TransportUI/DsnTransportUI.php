<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Plugin\TransportUI;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_transport\Attribute\TransportUI;
use Drupal\mailer_transport\UnifiedTransportFactoryInterface;
use Drupal\mailer_transport\AutowireTrait;

/**
 * Defines the DSN TransportUI plug-in.
 */
#[TransportUI(
  id: "dsn",
  label: new TranslatableMarkup("DSN"),
  description: new TranslatableMarkup("The DSN transport is a generic fallback and should only be used if there is no specific implementation available."),
)]
class DsnTransportUI extends TransportUIBase implements ContainerFactoryPluginInterface {

  use AutowireTrait;

  const DOCS_URL = 'https://symfony.com/doc/current/mailer.html#transport-setup';

  /**
   * Constructor.
   *
   * @param \Drupal\mailer_transport\UnifiedTransportFactoryInterface $factory
   *   The transport factory.
   * @param mixed ...$args
   *   Parent constructor arguments.
   *
   * @internal
   */
  public function __construct(
    protected readonly UnifiedTransportFactoryInterface $factory,
    ...$args,
  ) {
    parent::__construct(...$args);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'dsn' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['dsn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DSN'),
      '#maxlength' => 255,
      '#default_value' => $this->configuration['dsn'],
      '#description' => $this->t('DSN for the Transport, see <a href=":docs">documentation</a>.', [':docs' => static::DOCS_URL]),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $dsn = $form_state->getValue('dsn');
    if (parse_url($dsn, PHP_URL_SCHEME) == 'sendmail') {
      // Don't allow bypassing of the checks done by the Sendmail transport.
      $form_state->setErrorByName('dsn', $this->t('Use the Sendmail transport.'));
    }

    try {
      $this->factory->getTransport($dsn);
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('dsn', $this->t('Invalid DSN: @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['dsn'] = $form_state->getValue('dsn');
  }

  /**
   * {@inheritdoc}
   */
  public function getDsn(): string {
    return $this->configuration['dsn'];
  }

}
