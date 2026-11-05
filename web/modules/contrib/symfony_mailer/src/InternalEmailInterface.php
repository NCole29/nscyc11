<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * Defines an extended Email interface that adds internal functions.
 *
 * @internal
 */
interface InternalEmailInterface extends EmailInterface {

  /**
   * Runs processing of the current phase for all email processors.
   *
   * @return $this
   */
  public function process(): static;

  /**
   * Customizes the email.
   *
   * Valid: initialisation.
   *
   * @param string $langcode
   *   The language code.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return $this
   */
  public function customize(string $langcode, AccountInterface $account): static;

  /**
   * Renders the email.
   *
   * Valid: build.
   *
   * @return $this
   */
  public function render(): static;

  /**
   * Gets an array of 'suggestions'.
   *
   * The suggestions are used to select mailers, templates and policy
   * configuration in based on the email tag.
   *
   * @param string $initial
   *   The initial suggestion.
   * @param string $join
   *   The 'glue' to join each suggestion part with.
   *
   * @return array
   *   Suggestions, formed by taking the initial value and incrementally adding
   *   the parts of the tag breaking at each dot.
   */
  public function getSuggestions(string $initial, string $join): array;

  /**
   * Gets the inner Symfony email to send.
   *
   * Valid: after rendering.
   *
   * @return \Symfony\Component\Mime\Email
   *   Inner Symfony email.
   */
  public function getSymfonyEmail(): SymfonyEmail;

  /**
   * Sets the error message from sending the email.
   *
   * Valid: after sending.
   *
   * @param string $error
   *   The error message.
   *
   * @return $this
   */
  public function setError(string $error): static;

}
