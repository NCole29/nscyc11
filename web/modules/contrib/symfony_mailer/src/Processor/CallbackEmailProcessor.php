<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor;

use Drupal\symfony_mailer\EmailInterface;

/**
 * EmailProcessor that calls custom callback functions.
 */
class CallbackEmailProcessor implements EmailProcessorInterface {

  /**
   * Array of callback functions, keyed by email phase.
   *
   * @var ?callable[]
   */
  protected array $callbacks = [
    EmailInterface::PHASE_INIT => NULL,
    EmailInterface::PHASE_BUILD => NULL,
    EmailInterface::PHASE_POST_RENDER => NULL,
    EmailInterface::PHASE_POST_SEND => NULL,
  ];

  /**
   * CallbackEmailProcessor constructor.
   *
   * @param ?int $weight
   *   (Optional) The weight.
   * @param string $id
   *   (Optional) The ID.
   * @param ?callable $init
   *   (Optional) The callback for the initialisation phase.
   * @param ?callable $build
   *   (Optional) The callback for the build phase.
   * @param ?callable $postRender
   *   (Optional) The callback for the post-render phase.
   * @param ?callable $postSend
   *   (Optional) The callback for the post-send phase.
   */
  public function __construct(
    protected readonly int $weight = EmailInterface::DEFAULT_WEIGHT,
    protected ?string $id = NULL,
    ?callable $init = NULL,
    ?callable $build = NULL,
    ?callable $postRender = NULL,
    ?callable $postSend = NULL,
  ) {
    $this->setCallback($init, EmailInterface::PHASE_INIT);
    $this->setCallback($build, EmailInterface::PHASE_BUILD);
    $this->setCallback($postRender, EmailInterface::PHASE_POST_RENDER);
    $this->setCallback($postSend, EmailInterface::PHASE_POST_SEND);
  }

  /**
   * Sets the callback for the specified phase.
   *
   * @param ?callable $callback
   *   The callback. Pass NULL to delete the existing callback.
   * @param int $phase
   *   (Optional) The phase.
   *
   * @return $this
   */
  public function setCallback(?callable $callback, int $phase = EmailInterface::PHASE_BUILD): static {
    assert(array_key_exists($phase, $this->callbacks));
    $this->callbacks[$phase] = $callback;
    if (!$this->id) {
      // Automatically generate an ID.
      if ($callback instanceof \Closure) {
        $callback = [(new \ReflectionFunction($callback))->getClosureScopeClass(), 'closure'];
      }
      if (!is_array($callback)) {
        $callback = [$callback];
      }
      if (is_object($callback[0])) {
        $callback[0] = get_class($callback[0]);
      }
      $this->id = implode('::', $callback);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    if ($callback = $this->callbacks[EmailInterface::PHASE_INIT] ?? NULL) {
      $callback($email);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    if ($this->callbacks[EmailInterface::PHASE_BUILD] != NULL) {
      $this->callbacks[EmailInterface::PHASE_BUILD]($email);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    if ($this->callbacks[EmailInterface::PHASE_POST_RENDER] != NULL) {
      $this->callbacks[EmailInterface::PHASE_POST_RENDER]($email);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSend(EmailInterface $email): void {
    if ($this->callbacks[EmailInterface::PHASE_POST_SEND] != NULL) {
      $this->callbacks[EmailInterface::PHASE_POST_SEND]($email);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->id;
  }

}
