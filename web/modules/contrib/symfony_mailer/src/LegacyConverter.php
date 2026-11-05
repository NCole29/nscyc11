<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides the legacy mailer helper service.
 */
class LegacyConverter implements LegacyConverterInterface {

  /**
   * List of lower-cased address headers.
   *
   * Some address headers are stored directly in $message in addition to
   * $message['headers']. The array value indicates whether this is the case.
   */
  protected const array ADDRESS_HEADERS = [
    'from' => TRUE,
    'reply-to' => TRUE,
    'to' => TRUE,
    'cc' => FALSE,
    'bcc' => FALSE,
  ];

  /**
   * List of lower-cased headers to skip copying from the array.
   */
  protected const array SKIP_HEADERS = [
    // Set by Symfony mailer library.
    'content-transfer-encoding' => TRUE,
    'content-type' => TRUE,
    'date' => TRUE,
    'message-id' => TRUE,
    'mime-version' => TRUE,

    // Set by sending MTA.
    'return-path' => TRUE,

    // Doesn't belong in the headers array.
    'subject' => TRUE,
  ];

  /**
   * Regular expression for parsing addresses.
   *
   * Matches a string like 'Name <email@address.com>' Anything between the
   * first < and last > counts as the email address. This does not try to cover
   * all edge cases for address.
   */
  protected const FROM_STRING_PATTERN = '~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~';

  /**
   * {@inheritdoc}
   */
  public function emailToArray(EmailInterface $email, array &$message, bool $doParams = FALSE): void {
    $message['subject'] = $email->getSubject();
    if ($html = $email->getHtmlBody()) {
      $message['body'][0] = Markup::create($html);
    }
    elseif ($plain = $email->getTextBody()) {
      $message['body'][0] = $plain;
    }

    if ($doParams) {
      $message['params']['css'] = $email->getCss();

      // Attachments.
      foreach ($email->getAttachments() as $attachment) {
        if (!$attachment->hasAccess()) {
          continue;
        }

        if ($path = $attachment->getUri()) {
          $at['filepath'] = $path;
        }
        else {
          $at['filecontent'] = $attachment->getBody();
        }

        $at['filename'] = $attachment->getName();
        $at['filemime'] = $attachment->getMediaType() . '/' . $attachment->getMediaSubtype();
        $message['params']['attachments'][] = $at;
      }
    }

    // Headers.
    foreach ($email->getHeaders()->all() as $name => $header) {
      // Symfony stores headers in lower-case.
      if (isset(self::SKIP_HEADERS[$name])) {
        continue;
      }

      // Drupal message array stores headers in camel-case, except 'Reply-to'
      // is non-standard.
      $legacy_name = ($name == 'reply-to') ? 'Reply-to' : ucwords($name, '-');

      // Copy to 'headers'.
      $message['headers'][$legacy_name] = $header->getBodyAsString();

      if (!empty(self::ADDRESS_HEADERS[$name])) {
        // Also copy directly to $message, as lower-case.
        $message[$name] = $message['headers'][$legacy_name];
      }
    }

    // Drupal doesn't store the 'To' header in $message['headers'].
    unset($message['headers']['To']);
  }

  /**
   * {@inheritdoc}
   */
  public function emailFromArray(EmailInterface $email, array $message): void {
    $email->setSubject($message['subject']);
    $email->setBody($this->formatBody($message['body']));

    // Attachments.
    $attachments = $message['params']['attachments'] ?? [];
    foreach ($attachments as $attachment) {
      if (!empty($attachment['filepath'])) {
        $at = Attachment::fromPath($attachment['filepath'], $attachment['filename'] ?? NULL, $attachment['filemime'] ?? NULL);
        // On the legacy interface, the code that sets an attachment is
        // responsible for access checking.
        $at->setAccess(AccessResult::allowed());
        $email->attach($at);
      }
      elseif (!empty($attachment['filecontent'])) {
        $email->attach(Attachment::fromData($attachment["filecontent"], $attachment['filename'] ?? NULL, $attachment['filemime'] ?? NULL));
      }
    }

    // Headers.
    $src_headers = $message['headers'];
    $dest_headers = $email->getHeaders();

    // Add in 'To' header which is stored directly in the message.
    // @see \Drupal\Core\Mail\Plugin\Mail\PhpMail::mail()
    if (isset($message['to'])) {
      $src_headers['to'] = $message['to'];
    }

    foreach ($src_headers as $name => $value) {
      $name = strtolower($name);
      if (isset(self::SKIP_HEADERS[$name])) {
        continue;
      }

      if (isset(self::ADDRESS_HEADERS[$name])) {
        $email->setAddress($name, $this->parseAddress($value), TRUE);
      }
      else {
        $dest_headers->addHeader($name, $value);
      }
    }

    // Plain-text version.
    if (isset($message['plain'])) {
      // The legacy mail API permits a stringifiable Markup object here (core's
      // PhpMail cast it implicitly); setTextBody() requires a string.
      $email->setTextBody((string) $message['plain']);
    }

    // Parameters.
    foreach ($message['params'] as $key => $value) {
      $email->setParam($key, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function parseAddress(string $encoded, string $langcode = '', ?AccountInterface $account = NULL): array {
    // Split values by comma, but ignore commas encapsulated in double
    // quotes.
    $value = str_getcsv($encoded, escape: '\\');
    foreach ($value as $part) {

      if (empty(trim($part))) {
        continue;
      }
      // Code copied from \Symfony\Component\Mime\Address::create().
      if (strpos($part, '<')) {
        if (!preg_match(self::FROM_STRING_PATTERN, $part, $matches)) {
          throw new \InvalidArgumentException("Could not parse $part as an address.");
        }
        $addresses[] = new Address($matches['addrSpec'], trim($matches['displayName'], ' \'"'), $langcode, $account);
      }
      else {
        $addresses[] = new Address($part, '', $langcode, $account);
      }
    }
    return $addresses ?? [];
  }

  /**
   * Formats a legacy message body as a render array.
   *
   * The message body is received as an array of lines that are either strings
   * or objects implementing \Drupal\Component\Render\MarkupInterface. Any lines
   * that are not already safe markup are escaped using the fallback filter
   * format.
   *
   * @param array $body_array
   *   The array of lines.
   *
   * @return array
   *   The render array.
   *
   * @see \Drupal\Core\Mail\MailInterface::format()
   * @internal
   */
  public function formatBody(array $body_array): array {
    foreach ($body_array as $part) {
      if ($part instanceof MarkupInterface) {
        $body[] = ['#markup' => $part];
      }
      else {
        $body[] = [
          '#type' => 'processed_text',
          '#text' => $part,
        ];
      }
    }
    return $body ?? [];
  }

}
