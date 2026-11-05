<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Symfony\Component\Mime\Header\Headers;

/**
 * Defines an interface related to the Symfony Email object.
 *
 * The functions are mostly identical, except that set accessors are explicitly
 * named, e.g. setSubject() instead of subject(). Exceptions:
 * - No 'returnPath': should only be set by the SMTP server.
 *
 *   @see https://www.postmastery.com/about-the-return-path-header/
 * - No 'date': defaults automatically, can still override via getHeaders() if
 *   needed.
 * - Accept MarkupInterface for 'subject'.
 * - Remove all references to charset: always use utf-8.
 * - Remove all references to Symfony 'resource': these don't really apply in
 *   the Drupal environment.
 */
interface BaseEmailInterface {

  /**
   * Sets the email subject.
   *
   * The subject may be markup, in which case HTML content is stripped.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $subject
   *   The email subject.
   *
   * @return $this
   */
  public function setSubject($subject): static;

  /**
   * Gets the email subject.
   *
   * @return ?string
   *   The email subject, or NULL if not set.
   */
  public function getSubject(): ?string;

  /**
   * Sets the sender address.
   *
   * @param mixed $address
   *   The address to set.
   *
   * @return $this
   */
  public function setSender($address): static;

  /**
   * Gets the sender address.
   *
   * @return \Drupal\symfony_mailer\AddressInterface
   *   The sender address, or NULL if not set.
   */
  public function getSender(): ?AddressInterface;

  /**
   * Sets addresses for the specified header.
   *
   * @param string $name
   *   The name of the header to set.
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the specified header.
   *
   * @return $this
   */
  public function setAddress(string $name, $addresses): static;

  /**
   * Gets addresses for the specified header.
   *
   * @param string $name
   *   The name of the header to get.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The email addresses for the specified header.
   */
  public function getAddress(string $name): array;

  /**
   * Sets "from" addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the addresses.
   *
   * @return $this
   */
  public function setFrom($addresses): static;

  /**
   * Gets the "from" addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The "from" addresses.
   */
  public function getFrom(): array;

  /**
   * Sets "reply-to" addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the addresses.
   *
   * @return $this
   */
  public function setReplyTo($addresses): static;

  /**
   * Gets the "reply-to" addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The "reply-to" addresses.
   */
  public function getReplyTo(): array;

  /**
   * Sets "to" addresses.
   *
   * Valid: initialisation.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the addresses.
   *
   * @return $this
   */
  public function setTo($addresses): static;

  /**
   * Gets the "to" addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The "to" addresses.
   */
  public function getTo(): array;

  /**
   * Sets "cc" addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the addresses.
   *
   * @return $this
   */
  public function setCc($addresses): static;

  /**
   * Gets the "cc" addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The "cc" addresses.
   */
  public function getCc(): array;

  /**
   * Sets "bcc" addresses.
   *
   * @param mixed $addresses
   *   The addresses to set, see Address::convert(). Passing NULL as a value
   *   will erase the addresses.
   *
   * @return $this
   */
  public function setBcc($addresses): static;

  /**
   * Gets the "bcc" addresses.
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The "bcc" addresses.
   */
  public function getBcc(): array;

  /**
   * Sets the priority of this message.
   *
   * @param int $priority
   *   The priority, where 1 is the highest priority and 5 is the lowest.
   *
   * @return $this
   */
  public function setPriority(int $priority): static;

  /**
   * Get the priority of this message.
   *
   * @return int
   *   The priority, where 1 is the highest priority and 5 is the lowest.
   */
  public function getPriority(): int;

  /**
   * Sets the text body.
   *
   * By default, the text body will be generated from the unrendered body using
   * EmailInterface::getBody(). This function can be used to set a custom
   * plain-text alternative,
   *
   * @param string $body
   *   The text body.
   *
   * @return $this
   */
  public function setTextBody(string $body): static;

  /**
   * Gets the text body.
   *
   * @return string
   *   The text body, or NULL if not set.
   */
  public function getTextBody(): ?string;

  /**
   * Sets the HTML body.
   *
   * Valid: after rendering. Instead call EmailInterface::setBody() or related
   * functions before rendering.
   *
   * @param ?string $body
   *   (optional) The HTML body, or NULL to remove the HTML body.
   *
   * @return $this
   */
  public function setHtmlBody(?string $body): static;

  /**
   * Gets the HTML body.
   *
   * Valid: after rendering.
   *
   * @return string
   *   The HTML body, or NULL if not set.
   */
  public function getHtmlBody(): ?string;

  /**
   * Adds an attachment.
   *
   * This function automatically 'embeds' the attachment when needed.
   * - Any images in the email body with a 'src' attribute that matches the
   *   attachment filename are converted to references.
   * - The attachment is set to 'inline' if it is referenced.
   *
   * @param \Drupal\symfony_mailer\AttachmentInterface $attachment
   *   The attachment.
   *
   * @return $this
   */
  public function attach(AttachmentInterface $attachment): static;

  /**
   * Adds an attachment from a path.
   *
   * @param string $path
   *   The path to the file.
   * @param string|null $name
   *   (optional) The file name. Defaults to the base name of the path.
   * @param string|null $mimeType
   *   (optional) The MIME type. If omitted, the type will be guessed.
   *
   * @return $this
   */
  public function attachFromPath(string $path, ?string $name = NULL, ?string $mimeType = NULL): static;

  /**
   * Gets the attachments.
   *
   * @return \Drupal\symfony_mailer\AttachmentInterface[]
   *   The attachments. The key is the URI if there is one, else the content ID.
   */
  public function getAttachments(): array;

  /**
   * Removes an attachment.
   *
   * @param string $key
   *   The key to the attachment within the array returned by getAttachments().
   *
   * @return $this
   */
  public function removeAttachment(string $key): static;

  /**
   * Gets the headers object for getting or setting headers.
   *
   * @return \Symfony\Component\Mime\Header\Headers
   *   The headers object.
   */
  public function getHeaders(): Headers;

  /**
   * Adds a text header.
   *
   * @param string $name
   *   The name of the header.
   * @param string $value
   *   The header value.
   *
   * @return $this
   */
  public function addTextHeader(string $name, string $value): static;

}
