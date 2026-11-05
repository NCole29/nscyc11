<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor\automatic;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;

/**
 * Defines the Hooks Email Processor.
 */
class HooksEmailProcessor implements EmailProcessorInterface {

  use AutowireTrait;
  use EmailProcessorTrait;

  /**
   * HooksEmailProcessor constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   *
   * @internal
   */
  public function __construct(protected readonly ModuleHandlerInterface $moduleHandler) {}

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    $this->invokeHooks($email);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $this->invokeHooks($email);
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    $this->invokeHooks($email);
  }

  /**
   * {@inheritdoc}
   */
  public function postSend(EmailInterface $email): void {
    $this->invokeHooks($email);
  }

  /**
   * Invokes hooks for an email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email.
   *
   * @see hook_mailer_PHASE()
   * @see hook_mailer_TAG1_PHASE()
   * @see hook_mailer_TAG1__TAG2_PHASE()
   * @see hook_mailer_TAG1__TAG2__TAG3_PHASE()
   */
  protected function invokeHooks(EmailInterface $email): void {
    $name = EmailInterface::PHASE_NAMES[$email->getPhase()];
    $hooks = $email->getSuggestions("", "__");

    $this->moduleHandler->invokeAll("mailer_$name", [$email]);
    foreach ($hooks as $hook_variant) {
      $this->moduleHandler->invokeAll("mailer_{$hook_variant}_$name", [$email]);
    }
  }

}
