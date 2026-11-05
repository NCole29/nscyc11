<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Mime\Address as SymfonyAddress;

/**
 * Defines the class for an Email address.
 *
 * This class is used for the address headers on an email. For the to address,
 * it encodes extra information to customise the email for the recipients:
 * langcode and account.
 */
class Address implements AddressInterface {

  /**
   * The email address.
   */
  protected string $email;

  /**
   * The display name.
   */
  protected string $displayName;

  /**
   * The language code.
   */
  protected string $langcode;

  /**
   * The account, or NULL.
   */
  protected ?AccountInterface $account = NULL;

  /**
   * Constructs an address object.
   *
   * @param string $email
   *   The email address.
   * @param string $display_name
   *   (Optional) The display name.
   * @param string $langcode
   *   (Optional) The language code.
   * @param ?\Drupal\Core\Session\AccountInterface $account
   *   (Optional) The account.
   */
  public function __construct(string $email, string $display_name = '', string $langcode = '', ?AccountInterface $account = NULL) {
    $this->email = $email;
    $this->displayName = $display_name;
    $this->langcode = $langcode;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($address): static {
    if ($address instanceof AddressInterface) {
      return $address;
    }
    elseif (is_string($address)) {
      if ($address == '<site>') {
        $site_config = \Drupal::config('system.site');
        $site_mail = $site_config->get('mail') ?: ini_get('sendmail_from');
        return new static($site_mail, $site_config->get('name'));
      }
      elseif ($user = user_load_by_mail($address)) {
        return static::create($user);
      }
      else {
        return new static($address);
      }
    }
    elseif ($address instanceof AccountInterface) {
      return new static($address->getEmail(), $address->getDisplayName(), $address->getPreferredLangcode(), $address);
    }
    elseif ($address instanceof SymfonyAddress) {
      return new static($address->getAddress(), $address->getName());
    }
    else {
      throw new \LogicException('Cannot convert to address.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    return $this->email;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName(): string {
    return $this->displayName;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): ?AccountInterface {
    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function getSymfony(): SymfonyAddress {
    return new SymfonyAddress($this->email, $this->displayName);
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->getSymfony()->toString();
  }

  /**
   * {@inheritdoc}
   */
  public static function convert($addresses): array {
    $result = [];

    if (!is_array($addresses)) {
      $addresses = [$addresses];
    }

    foreach ($addresses as $address) {
      $result[] = static::create($address);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromCurrent(string $email, string $display_name): static {
    $user = \Drupal::currentUser();
    if ($user->isAnonymous()) {
      return new static($email, $display_name, \Drupal::languageManager()->getCurrentLanguage()->getId());
    }
    return static::create($user);
  }

}
