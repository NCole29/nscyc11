<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Provides the interface for the mailer info service.
 */
interface MailerLookupInterface extends DiscoveryInterface {

  /**
   * Gets a definition or sub-definition for the specified tag.
   *
   * @param string $tag
   *   The tag.
   *
   * @return array
   *   The definition.
   */
  public function getTagDefinition(string $tag): array;

  /**
   * Gets the mailer service for the specified tag.
   *
   * @param string $tag
   *   The tag.
   *
   * @return \Drupal\symfony_mailer\Component\ComponentMailerInterface
   *   The mailer.
   */
  public function getMailerService(string $tag): ComponentMailerInterface;

  /**
   * Returns the parent tag.
   *
   * @param string $tag
   *   The initial tag.
   *
   * @return string
   *   The parent tag.
   */
  public function parentTag(string $tag): string;

}
