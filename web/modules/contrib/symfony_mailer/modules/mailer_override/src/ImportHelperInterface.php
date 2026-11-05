<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Import helper service interface.
 */
interface ImportHelperInterface {

  /**
   * Parses an address string into Address structures.
   *
   * Convenience wrapper to LegacyConverterInterface::parseAddress. See the
   * comment there for important notes.
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

  /**
   * Converts an address array into Policy configuration.
   *
   * This function should only be used for migration.
   *
   * @param \Drupal\symfony_mailer\Address[] $addresses
   *   Array of address structures.
   *
   * @return array
   *   The equivalent policy configuration.
   */
  public function policyFromAddresses(array $addresses): array;

  /**
   * Converts an plain-text email body into Policy configuration.
   *
   * This function should only be used for migration.
   *
   * @param string $body
   *   The body.
   *
   * @return array
   *   The equivalent policy configuration.
   */
  public function policyFromPlainBody(string $body): array;

  /**
   * Returns the configuration factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function config(): ConfigFactoryInterface;

}
