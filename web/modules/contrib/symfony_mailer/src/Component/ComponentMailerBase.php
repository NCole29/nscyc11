<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\Processor\CallbackEmailProcessor;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;

/**
 * Defines the base class for Mailer plug-ins.
 */
abstract class ComponentMailerBase implements ComponentMailerInterface {

  use EmailProcessorTrait;

  /**
   * {@inheritdoc}
   */
  const DEFAULT_WEIGHT = 300;

  /**
   * Email processors to add to the next email that is sent.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailProcessorInterface[]
   */
  protected array $processors = [];

  /**
   * Constructs a component mailer.
   */
  public function __construct(
    protected readonly MailerPlusInterface $mailer,
    protected readonly MailerLookupInterface $mailerLookup,
    protected readonly string $baseTag,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addProcessor(EmailProcessorInterface $processor): static {
    $this->processors[] = $processor;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addCallback(callable $function, int $phase = EmailInterface::PHASE_BUILD, int $weight = EmailInterface::DEFAULT_WEIGHT, ?string $id = NULL): static {
    $this->addProcessor((new CallbackEmailProcessor($weight, $id))->setCallback($function, $phase));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseTag(): string {
    return $this->baseTag;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    return static::DEFAULT_WEIGHT;
  }

  /**
   * Creates a new email.
   *
   * @param string $sub_tag
   *   Sub-tag to add onto the plugin ID.
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   The email.
   */
  protected function newEmail(string $sub_tag): EmailInterface {
    $email = $this->mailer->newEmail($this->getBaseTag() . ".$sub_tag")->addProcessor($this);

    foreach ($this->processors as $processor) {
      $email->addProcessor($processor);
    }
    $this->processors = [];

    return $email;
  }

}
