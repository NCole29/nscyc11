<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * Defines the class for an Email attachment.
 */
class Attachment extends DataPart implements AttachmentInterface {

  /**
   * The access result.
   */
  protected AccessResultInterface $access;

  /**
   * The path, converted to a full URL with a scheme where possible.
   */
  protected ?string $path = NULL;

  /**
   * {@inheritdoc}
   */
  protected function __construct($body, ?string $name = NULL, ?string $mimeType = NULL) {
    $this->access = AccessResult::neutral();
    parent::__construct($body, $name, $mimeType);
  }

  /**
   * {@inheritdoc}
   */
  public static function fromPath(string $path, ?string $name = NULL, ?string $mimeType = NULL, bool $isUri = FALSE): self {
    if (!parse_url($path, PHP_URL_SCHEME)) {
      if ($isUri) {
        // Convert a site-relative URL to absolute so that we can call fopen().
        $path = \Drupal::request()->getSchemeAndHttpHost() . $path;
      }
      else {
        // Try to find a URI for a local file.
        try {
          $url_generator = \Drupal::service('file_url_generator');
          $uri = $url_generator->generateAbsoluteString($path);
        }
        catch (InvalidStreamWrapperException $e) {
        }
      }
    }

    $attachment = new static(new File($path), $name, $mimeType);
    $attachment->path = $uri ?? $path;
    return $attachment;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromData(string $data, ?string $name = NULL, ?string $mimeType = NULL): self {
    return new static($data, $name, $mimeType);
  }

  /**
   * {@inheritdoc}
   */
  public function setAccess(AccessResultInterface $access): static {
    $this->access = $this->access->orIf($access);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccess(): bool {
    return $this->access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getUri(): ?string {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   *
   * Serialization is intended only for testing.
   *
   * @internal
   */
  public function __serialize(): array {
    return [$this->getName(), $this->path, $this->hasAccess()];
  }

  /**
   * {@inheritdoc}
   */
  public function __unserialize(array $data): void {
    [$name, $this->path, $access] = $data;
    $this->setName($name);
    $this->access = $access ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
