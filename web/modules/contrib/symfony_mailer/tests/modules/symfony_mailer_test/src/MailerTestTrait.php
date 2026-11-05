<?php

namespace Drupal\symfony_mailer_test;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Tracks sent emails for testing.
 */
trait MailerTestTrait {

  /**
   * The emails that have been sent and not yet checked.
   *
   * @var \Symfony\Component\Mime\Email[]
   */
  protected ?array $emails = NULL;

  /**
   * The most recently sent email.
   */
  protected Email $email;

  /**
   * An xpath for the most recently sent email HTML body.
   */
  protected ?\DOMXPath $xpath;

  /**
   * Gets the next email, removing it from the list.
   *
   * @param bool $last
   *   (optional)TRUE to assert that this is the last email.
   *
   * @return \Symfony\Component\Mime\Email
   *   The email.
   */
  public function readMail(bool $last = TRUE): Email {
    $this->init();
    $this->assertNotEmpty($this->emails);
    $this->email = array_shift($this->emails);
    $this->xpath = NULL;

    if ($last) {
      $this->noMail();
    }
    return $this->email;
  }

  /**
   * Checks that the most recently sent email contains text.
   *
   * @param string $value
   *   Text to check for.
   *
   * @return $this
   */
  public function assertBodyContains(string $value): static {
    $this->assertStringContainsString($value, $this->email->getHtmlBody());
    return $this;
  }

  /**
   * Checks that the most recently sent email does not contain text.
   *
   * @param string $value
   *   Text to check for.
   *
   * @return $this
   */
  public function assertBodyNotContains(string $value): static {
    $this->assertStringNotContainsString($value, $this->email->getHtmlBody());
    return $this;
  }

  /**
   * Gets an XPath object for the HTML body.
   *
   * @return \DOMXPath
   *   The XPath object.
   */
  public function getXpath(): \DOMXPath {
    if (!$this->xpath) {
      $dom = new \DOMDocument();
      $dom->loadHTML($this->email->getHtmlBody());
      $this->xpath = new \DOMXPath($dom);
    }
    return $this->xpath;
  }

  /**
   * Finds the first link with the specified text.
   *
   * @return string
   *   The link.
   */
  public function findLink(string $text): string {
    $nodes = $this->getXpath()->query("//a[text()='$text']");
    $this->assertNotEmpty($nodes->count());
    return $nodes->item(0)->getAttribute('href');
  }

  /**
   * Checks the subject of the most recently sent email.
   *
   * @param string $value
   *   Text to check for.
   *
   * @return $this
   */
  public function assertSubject(string $value): static {
    $this->assertEquals($value, $this->email->getSubject());
    return $this;
  }

  /**
   * Checks the specified address of the most recently sent email.
   *
   * @param string $name
   *   The address header.
   * @param mixed $expected
   *   The email addresses.
   *
   * @return $this
   */
  public function assertAddress(string $name, $expected): static {
    $actual = $this->email->getHeaders()->getHeaderBody($name);
    if (!$expected) {
      $this->assertNull($actual);
    }
    else {
      if (!is_countable($expected)) {
        $expected = [$expected];
      }
      if (!is_countable($actual)) {
        $actual = [$actual];
      }
      $this->assertEquals(count($expected), count($actual));

      foreach ($actual as $index => $loop_actual) {
        // Index of the addresses must be preserved.
        $loop_expected = Address::create($expected[$index]);

        $this->assertEquals($loop_expected->getAddress(), $loop_actual->getAddress());
        $this->assertEquals($loop_expected->getName(), $loop_actual->getName());
      }
    }

    return $this;
  }

  /**
   * Checks for the specified attachment on the most recently sent email.
   *
   * @param ?string $uri
   *   The URI.
   * @param ?string $name
   *   The name.
   * @param ?string $mimeType
   *   The MIME type.
   * @param bool $embed
   *   If TRUE, then assert that this attachment is embedded.
   *
   * @return $this
   */
  public function assertAttachment(?string $uri = NULL, ?string $name = NULL, ?string $mimeType = NULL, bool $embed = FALSE): static {
    if ($name == NULL) {
      $name = basename($uri);
    }

    foreach ($this->email->getAttachments() as $attachment) {
      if (($attachment->getUri() == $uri) && ($attachment->getName() == $name)) {
        if ($mimeType) {
          $this->assertEquals($mimeType, $attachment->getContentType());
        }
        if ($embed) {
          $this->assertBodyContains('src="cid:' . $attachment->getContentId());
        }

        return $this;
      }

      $message[] = '[' . $attachment->getFilename() . ',' . $attachment->getName() . ']';
    }

    $message = "Actual attachments: " . implode(',', $message ?? []);
    $this->fail($message);
  }

  /**
   * Checks an attachment is not included on the most recently sent email.
   *
   * @param ?string $uri
   *   The URI that should not be included.
   * @param ?string $name
   *   The name that should not be included.
   *
   * @return $this
   */
  public function assertNoAttachment(?string $uri = NULL, ?string $name = NULL): static {
    foreach ($this->email->getAttachments() as $attachment) {
      if ($uri != NULL) {
        $this->assertNotEquals($uri, $attachment->getFilename());
      }
      if ($name != NULL) {
        $this->assertNotEquals($name, $attachment->getName());
      }
    }
    return $this;
  }

  /**
   * Checks 'sender' address of the most recently sent email.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return $this
   */
  public function assertSender(string $email, string $display_name = ''): static {
    $this->assertAddress('sender', new Address($email, $display_name));
    return $this;
  }

  /**
   * Checks 'reply-to' address of the most recently sent email.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return $this
   */
  public function assertReplyTo(string $email, string $display_name = ''): static {
    $this->assertAddress('reply-to', new Address($email, $display_name));
    return $this;
  }

  /**
   * Checks 'to' address of the most recently sent email.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return $this
   */
  public function assertTo(string $email, string $display_name = ''): static {
    $this->assertAddress('to', new Address($email, $display_name));
    return $this;
  }

  /**
   * Checks 'cc' address of the most recently sent email.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return $this
   */
  public function assertCc(string $email, string $display_name = ''): static {
    $this->assertAddress('cc', new Address($email, $display_name));
    return $this;
  }

  /**
   * Checks 'bcc' address of the most recently sent email.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return $this
   */
  public function assertBcc(string $email, string $display_name = ''): static {
    $this->assertAddress('bcc', new Address($email, $display_name));
    return $this;
  }

  /**
   * Checks langcode of the most recently sent email.
   *
   * @param string $value
   *   Text to check for.
   *
   * @return $this
   */
  public function assertLangcode(string $value): static {
    $this->assertEquals($value, $this->email->getLangcode());
    return $this;
  }

  /**
   * Converts a URI to absolute.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string
   *   The absolute URI.
   */
  public function absoluteUri(string $uri): string {
    // Match Attachment::fromPath().
    if (!parse_url($uri, PHP_URL_SCHEME)) {
      return \Drupal::request()->getSchemeAndHttpHost() . $uri;
    }
    return $uri;
  }

  /**
   * Checks there are no more emails.
   */
  protected function noMail(): void {
    $this->init();
    $this->assertCount(0, $this->emails, 'All emails have been checked.');
    $this->emails = NULL;
  }

  /**
   * Initializes the list of emails.
   */
  protected function init(): void {
    if (is_null($this->emails)) {
      $this->emails = $this->getMails();
      \Drupal::keyValue('symfony_mailer_test')->delete('emails');
    }
  }

  /**
   * Gets an array containing all emails sent during this test case.
   *
   * @return \Symfony\Component\Mime\Email[]
   *   An array containing email messages captured during the current test.
   *
   * @see \Drupal\symfony_mailer_test\Transport\CaptureTransport
   */
  protected function getMails(): array {
    return \Drupal::keyValue('symfony_mailer_test')->get('emails', []);
  }

}
