<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Mime\Address as SymfonyAddress;

/**
 * Defines the interface for an Email address.
 */
interface AddressInterface {

  /**
   * Creates an address from various other data types.
   *
   * @param mixed $address
   *   The input address, one of the following:
   *   - \Drupal\symfony_mailer\AddressInterface
   *   - string containing a single email address without display name
   *   - \Drupal\Core\Session\AccountInterface
   *   - \Symfony\Component\Mime\Address.
   *
   * @return \Drupal\symfony_mailer\AddressInterface
   *   The address.
   */
  public static function create($address): static;

  /**
   * Gets the email address of this address.
   *
   * @return string
   *   The email address.
   */
  public function getEmail(): string;

  /**
   * Gets the display name of this address.
   *
   * @return string
   *   The display name, or an empty string if there isn't one.
   */
  public function getDisplayName(): string;

  /**
   * Gets the language code of this address.
   *
   * @return string
   *   The language code, or an empty string if there isn't one.
   */
  public function getLangcode(): string;

  /**
   * Gets the account associated with the recipient of this email.
   *
   * @return ?\Drupal\Core\Session\AccountInterface
   *   The account or NULL is there isn't one.
   */
  public function getAccount(): ?AccountInterface;

  /**
   * Gets a Symfony address object from this address.
   *
   * @return \Symfony\Component\Mime\Address
   *   The Symfony address.
   */
  public function getSymfony(): SymfonyAddress;

  /**
   * Converts one or more addresses.
   *
   * @param mixed $addresses
   *   The addresses to set. Can be a single element or an array of data types
   *   accepted by static::create().
   *
   * @return \Drupal\symfony_mailer\AddressInterface[]
   *   The converted addresses.
   */
  public static function convert($addresses): array;

  /**
   * Creates an address from the current environment.
   *
   * If a user is logged in, then use that. Else use the passed arguments
   * plus the current language.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   *
   * @return static
   */
  public static function fromCurrent(string $email, string $display_name): static;

}
