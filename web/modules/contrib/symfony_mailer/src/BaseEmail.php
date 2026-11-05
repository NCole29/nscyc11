<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\PlainTextOutput;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Email;

/**
 * Class that decorates the Symfony Email object with some adaptation.
 */
class BaseEmail implements BaseEmailInterface {

  /**
   * The inner Symfony Email object.
   */
  protected Email $inner;

  /**
   * Current phase, one of the PHASE_ constants.
   */
  protected int $phase = EmailInterface::PHASE_INIT;

  /**
   * The addresses.
   */
  protected array $addresses = [
    'from' => [],
    'reply-to' => [],
    'to' => [],
    'cc' => [],
    'bcc' => [],
    'sender' => [],
  ];

  /**
   * The attachments.
   *
   * @var \Symfony\Component\Mime\Part\DataPart[]
   */
  protected array $attachments = [];

  /**
   * {@inheritdoc}
   */
  public function setSubject($subject): static {
    $this->valid();

    if ($subject instanceof MarkupInterface) {
      $subject = PlainTextOutput::renderFromHtml($subject);
    }

    $this->inner->subject($subject);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject(): ?string {
    return $this->inner->getSubject();
  }

  /**
   * {@inheritdoc}
   */
  public function setSender($address): static {
    return $this->setAddress('sender', [$address]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSender(): ?AddressInterface {
    return $this->getAddress('sender')[0] ?? NULL;
  }

  /**
   * {@inheritdoc}
   *
   * The $legacy parameter is @internal and may be removed at any time.
   */
  public function setAddress(string $name, $addresses, bool $legacy = FALSE): static {
    $name = strtolower($name);
    assert(isset($this->addresses[$name]));

    if ($name === 'sender') {
      assert(count($addresses) <= 1);
    }

    // Allow late setting of the to address for legacy emails. The langcode
    // will not be updated, however that is a limitation of the legacy mail
    // system.
    if (!$legacy && $name == 'to') {
      $this->valid(EmailInterface::PHASE_INIT);
    }
    else {
      $this->valid();
    }

    // Either erasing all addresses or updating them for the specified header.
    $this->addresses[$name] = is_null($addresses) ? [] : Address::convert($addresses);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddress(string $name): array {
    $name = strtolower($name);
    assert(isset($this->addresses[$name]));
    return $this->addresses[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function setFrom($addresses): static {
    return $this->setAddress('from', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getFrom(): array {
    return $this->addresses['from'];
  }

  /**
   * {@inheritdoc}
   */
  public function setReplyTo($addresses): static {
    return $this->setAddress('reply-to', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getReplyTo(): array {
    return $this->addresses['reply-to'];
  }

  /**
   * {@inheritdoc}
   */
  public function setTo($addresses): static {
    return $this->setAddress('to', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getTo(): array {
    return $this->addresses['to'];
  }

  /**
   * {@inheritdoc}
   */
  public function setCc($addresses): static {
    return $this->setAddress('cc', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getCc(): array {
    return $this->addresses['cc'];
  }

  /**
   * {@inheritdoc}
   */
  public function setBcc($addresses): static {
    return $this->setAddress('bcc', $addresses);
  }

  /**
   * {@inheritdoc}
   */
  public function getBcc(): array {
    return $this->addresses['bcc'];
  }

  /**
   * {@inheritdoc}
   */
  public function setPriority(int $priority): static {
    $this->valid();
    $this->inner->priority($priority);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    return $this->inner->getPriority();
  }

  /**
   * {@inheritdoc}
   */
  public function setTextBody(string $body): static {
    $this->valid();
    $this->inner->text($body);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTextBody(): ?string {
    return $this->inner->getTextBody();
  }

  /**
   * {@inheritdoc}
   */
  public function setHtmlBody(?string $body): static {
    $this->valid(min_phase: EmailInterface::PHASE_POST_RENDER);
    $this->inner->html($body);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlBody(): ?string {
    $this->valid(EmailInterface::PHASE_POST_SEND, EmailInterface::PHASE_POST_RENDER);
    return $this->inner->getHtmlBody();
  }

  /**
   * {@inheritdoc}
   */
  public function attachFromPath(string $path, ?string $name = NULL, ?string $mimeType = NULL): static {
    return $this->attach(Attachment::fromPath($path, $name, $mimeType));
  }

  /**
   * {@inheritdoc}
   */
  public function attach(AttachmentInterface $attachment): static {
    $this->valid();
    $key = $attachment->getUri() ?: $attachment->getContentId();
    $this->attachments[$key] = $attachment;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachments(): array {
    return $this->attachments;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAttachment(string $key): static {
    $this->valid();
    unset($this->attachments[$key]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaders(): Headers {
    return $this->inner->getHeaders();
  }

  /**
   * {@inheritdoc}
   */
  public function addTextHeader(string $name, string $value): static {
    $this->valid();
    $this->getHeaders()->addTextHeader($name, $value);
    return $this;
  }

  /**
   * Checks that a function was called in the correct phase.
   *
   * @param int $max_phase
   *   (Optional) Latest allowed phase, one of the PHASE_ constants.
   * @param ?int $min_phase
   *   (Optional) Earliest allowed phase, one of the PHASE_ constants.
   *
   * @return $this
   */
  protected function valid(int $max_phase = EmailInterface::PHASE_POST_RENDER, ?int $min_phase = NULL): static {
    $min_phase ??= min($max_phase, EmailInterface::PHASE_BUILD);
    $valid = ($this->phase <= $max_phase) && ($this->phase >= $min_phase);

    if (!$valid) {
      $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
      throw new \LogicException("$caller function is only valid in phases $min_phase-$max_phase, called in $this->phase.");
    }
    return $this;
  }

}
