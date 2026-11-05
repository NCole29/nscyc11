<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;
use Drupal\symfony_mailer\LegacyConverterInterface;

/**
 * Defines the Override plug-in for user module.
 */
abstract class OverrideBase extends PluginBase implements OverrideInterface, ContainerFactoryPluginInterface {

  use AutowireTrait;

  /**
   * The mailer.
   */
  protected ComponentMailerInterface $mailer;

  /**
   * Constructor.
   *
   * @param \Drupal\symfony_mailer\MailerLookupInterface $mailerLookup
   *   The mailer lookup service.
   * @param \Drupal\symfony_mailer\LegacyConverterInterface $legacyConverter
   *   The legacy converter.
   * @param mixed ...$args
   *   Parent constructor arguments.
   *
   * @internal
   */
  public function __construct(
    protected readonly MailerLookupInterface $mailerLookup,
    protected readonly LegacyConverterInterface $legacyConverter,
    ...$args,
  ) {
    parent::__construct(...$args);
  }

  /**
   * {@inheritdoc}
   */
  public function send(array &$message, EmailProcessorInterface $processor): bool {
    $this->mailer = $this->mailerLookup->getMailerService($this->getPluginId());
    $this->mailer->addProcessor($processor);
    return $this->fromArray($message);
  }

  /**
   * Converts from an legacy message array to send an email.
   *
   * @param array $message
   *   The array to send from.
   *
   * @return bool
   *   Whether successful.
   */
  abstract protected function fromArray(array $message): bool;

}
