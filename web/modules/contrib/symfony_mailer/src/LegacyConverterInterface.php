<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\Session\AccountInterface;

/**
 * Provides the legacy converter service.
 */
interface LegacyConverterInterface {

  /**
   * Fills a message array from an Email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to fill from.
   * @param array $message
   *   The array to fill.
   * @param bool $doParams
   *   Whether to fill $message['params'].
   */
  public function emailToArray(EmailInterface $email, array &$message, bool $doParams = FALSE): void;

  /**
   * Fills an Email from a message array.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to fill.
   * @param array $message
   *   The array to fill from.
   */
  public function emailFromArray(EmailInterface $email, array $message): void;

  /**
   * Parses an address string into Address structures.
   *
   * IMPORTANT: New code should NOT use this function. Normally you would use
   * Mailer Policy to configure addresses. Or if you store them directly,
   * use 'human-readable' format with a list of addresses and display names.
   *
   * This function should only be used inside the import() function of an
   * Override plugin (or if the address string has been received from outside
   * code that you can't change). It is used when the old code has already
   * encoded the addresses to a string. This function converts back to
   * human-readable format, ready for the symfony mailer library to encode once
   * more during sending! This is inefficient, and subject to limitations as
   * documented below.
   *
   * @todo This function is limited. It cannot handle display names, or emails
   * with characters that require special encoding.
   *
   * @param string $encoded
   *   Encoded address string.
   * @param string $langcode
   *   (Optional) Language code to add to the address.
   * @param ?\Drupal\Core\Session\AccountInterface $account
   *   (Optional) Account to add to the address.
   *
   * @return \Drupal\symfony_mailer\Address[]
   *   The parsed address structures.
   */
  public function parseAddress(string $encoded, string $langcode = '', ?AccountInterface $account = NULL): array;

}
