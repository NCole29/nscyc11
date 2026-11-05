<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\Mailer;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mailer_override\ImportHelperInterface;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Component\ComponentMailerBase;
use Drupal\symfony_mailer\LegacyConverterInterface;

/**
 * Legacy Mailer plug-in that uses a message array.
 */
class LegacyMailer extends ComponentMailerBase implements LegacyMailerInterface, ContainerFactoryPluginInterface {

  use AutowireTrait;

  /**
   * LegacyMailer constructor.
   *
   * @param \Drupal\symfony_mailer\LegacyConverterInterface $legacyConverter
   *   The legacy converter.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param mixed ...$args
   *   Parent constructor arguments.
   *
   * @internal
   */
  public function __construct(
    protected readonly LegacyConverterInterface $legacyConverter,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly AccountInterface $account,
    ...$args,
  ) {
    parent::__construct(...$args);
  }

  /**
   * {@inheritdoc}
   */
  public function send(array $message): bool {
    $email = $this->newEmail($message['key'])
      ->setParam('legacy_message', $message);

    // The 'To' header is stored directly in the message.
    // @see \Drupal\Core\Mail\Plugin\Mail\PhpMail::mail()
    if (isset($message['to'])) {
      $email->setTo(\Drupal::service(ImportHelperInterface::class)->parseAddress($message['to'], $message['langcode'], $this->account));
    }
    return $email->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $message = $email->getParam('legacy_message');
    $message += [
      'subject' => '',
      'body' => [],
      'headers' => [],
    ];

    if ($reply = $message['reply-to']) {
      // Match the non-standard lower-case 't' used by Drupal Core.
      $message['headers']['Reply-to'] = $reply;
    }

    // Build the email by invoking hook_mail() on this module.
    $args = [$message['key'], &$message, $message['params']];
    $this->moduleHandler->invoke($message['module'], 'mail', $args);

    // Invoke hook_mail_alter() to allow all modules to alter the resulting
    // email.
    $this->moduleHandler->alter('mail', $message);

    if (!$message['send']) {
      throw new SkipMailException('Send aborted by hook_mail().');
    }

    // Fill the email from the message array.
    $this->legacyConverter->emailFromArray($email, $message);
  }

}
