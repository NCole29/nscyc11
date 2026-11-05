<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;

/**
 * Defines the interface for an Email.
 */
interface EmailInterface extends BaseEmailInterface {

  /**
   * The default weight for an email processor.
   */
  const DEFAULT_WEIGHT = 500;

  /**
   * Initialisation phase: set the processing that will occur.
   *
   * Set processors, parameters, theme and destination addresses.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::init()
   */
  const PHASE_INIT = 0;

  /**
   * Build phase: construct the email.
   *
   * The language, theme, and account are now correct. The body is not yet
   * rendered and stored as a Drupal render array.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::build()
   */
  const PHASE_BUILD = 1;

  /**
   * Post-render phase: adjust rendered output.
   *
   * Act on the rendered HTML, or any header.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::postRender()
   */
  const PHASE_POST_RENDER = 3;

  /**
   * Post-send phase: no further alterations allowed.
   *
   * @see \Drupal\symfony_mailer\Processor\EmailProcessorInterface::postSend()
   */
  const PHASE_POST_SEND = 4;

  /**
   * Names of the email phases.
   */
  const PHASE_NAMES = [
    self::PHASE_INIT => 'init',
    self::PHASE_BUILD => 'build',
    self::PHASE_POST_RENDER => 'post_render',
    self::PHASE_POST_SEND => 'post_send',
  ];

  /**
   * Gets the phase of processing.
   *
   * @return int
   *   The phase, one of the PHASE_ constants.
   */
  public function getPhase(): int;

  /**
   * Adds an email processor.
   *
   * Valid: initialisation.
   *
   * @param \Drupal\symfony_mailer\Processor\EmailProcessorInterface $processor
   *   The email processor.
   *
   * @return $this
   */
  public function addProcessor(EmailProcessorInterface $processor): static;

  /**
   * Adds a callback function for a specified phase.
   *
   * Valid: initialisation.
   *
   * @param callable $function
   *   The function to call.
   * @param int $phase
   *   (Optional) The phase to run in, one of the EmailInterface::PHASE_
   *   constants.
   * @param int $weight
   *   (Optional) The weight, lower values run earlier.
   * @param string $id
   *   (Optional) A unique ID.
   *
   * @return $this
   */
  public function addCallback(callable $function, int $phase = self::PHASE_BUILD, int $weight = self::DEFAULT_WEIGHT, ?string $id = NULL): static;

  /**
   * Removes an email processor.
   *
   * Valid: initialisation.
   *
   * @param string $id
   *   The email processor ID.
   *
   * @return $this
   */
  public function removeProcessor(string $id): static;

  /**
   * Gets the email processors.
   *
   * @return \Drupal\symfony_mailer\Processor\EmailProcessorInterface[]
   *   The processors.
   */
  public function getProcessors(): array;

  /**
   * Gets the langcode.
   *
   * The langcode is calculated automatically from the to address.
   *
   * @return string
   *   Language code to use to compose the email.
   */
  public function getLangcode(): string;

  /**
   * Sets parameters used for building the email.
   *
   * Valid: before sending.
   *
   * @param array $params
   *   (optional) An array of keyed objects or configuration.
   *
   * @return $this
   */
  public function setParams(array $params = []): static;

  /**
   * Sets a parameter used for building the email.
   *
   * If the value is an entity, then use setEntityParam() instead.
   *
   * Valid: before sending.
   *
   * @param string $key
   *   Parameter key to set.
   * @param mixed $value
   *   Parameter value to set, typically a setting that alters the email build.
   *
   * @return $this
   */
  public function setParam(string $key, $value): static;

  /**
   * Sets a parameter from an entity.
   *
   * Valid: before sending.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Parameter value to set. The key is the entity type ID.
   *
   * @return $this
   */
  public function setEntityParam(EntityInterface $entity): static;

  /**
   * Gets parameters used for building the email.
   *
   * @return array
   *   An array of keyed objects.
   */
  public function getParams(): array;

  /**
   * Gets a parameter used for building the email.
   *
   * @param string $key
   *   Parameter key to get.
   *
   * @return mixed
   *   Parameter value, or NULL if the parameter is not set.
   */
  public function getParam(string $key);

  /**
   * Sends the email.
   *
   * Valid: initialisation.
   *
   * @return bool
   *   Whether successful.
   */
  public function send(): bool;

  /**
   * Gets the account associated with the recipient of this email.
   *
   * The account is calculated automatically from the to address.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The account.
   */
  public function getAccount(): AccountInterface;

  /**
   * Sets the unrendered email body array.
   *
   * The email body will be rendered using a template, then used to form both
   * the HTML and plain text body parts. This process can be customised by the
   * email adjusters added to the email.
   *
   * Valid: build.
   *
   * @param array $body
   *   Unrendered email body array.
   *
   * @return $this
   */
  public function setBody(array $body): static;

  /**
   * Gets the unrendered email body array.
   *
   * Valid: build.
   *
   * @return array
   *   Body render array.
   */
  public function getBody(): array;

  /**
   * Sets variables available in the email template.
   *
   * Valid: initialisation or build.
   *
   * @param array $variables
   *   An array of keyed variables.
   *
   * @return $this
   */
  public function setVariables(array $variables): static;

  /**
   * Sets a variable available in the email template.
   *
   * Valid: initialisation or build.
   *
   * @param string $key
   *   Variable key to set.
   * @param mixed $value
   *   Variable value to set.
   *
   * @return $this
   */
  public function setVariable(string $key, $value): static;

  /**
   * Sets a variable by rendering an entity.
   *
   * Calls getParam() the parameter with the specified key, renders the value,
   * then calls setVariable() with the result.
   *
   * Valid: build.
   *
   * @param string $key
   *   The key.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the entity.
   *
   * @return $this
   */
  public function setEntityVariable(string $key, $view_mode = 'email'): static;

  /**
   * Gets variables available in the email template.
   *
   * @return array
   *   An array of keyed variables.
   */
  public function getVariables(): array;

  /**
   * Gets the email tag, used to identify the type or source of this email.
   *
   * The value should be a sequence of identifiers joined by dots. The first
   * should be the module name, then the module can choose how to allocate
   * subsequent values.
   *
   * @param ?int $part
   *   If set, split the tag by dots, and return the nth part.
   *
   * @return string
   *   Email tag.
   */
  public function getTag(?int $part = NULL): string;

  /**
   * Sets the email theme.
   *
   * Valid: initialisation.
   *
   * @param string $theme_name
   *   The theme name to use for email.
   *
   * @return $this
   */
  public function setTheme(string $theme_name): static;

  /**
   * Gets the email theme name.
   *
   * @return string
   *   The email theme name.
   */
  public function getTheme(): string;

  /**
   * Adds an asset library to use as mail CSS.
   *
   * Valid: build.
   *
   * @param string $library
   *   Library name, in the form "THEME/LIBRARY".
   *
   * @return $this
   */
  public function addLibrary(string $library): static;

  /**
   * Gets the libraries to use as mail CSS.
   *
   * @return array
   *   Array of library names, in the form "THEME/LIBRARY".
   */
  public function getLibraries(): array;

  /**
   * Sets the mailer transport to use.
   *
   * @param string $transport
   *   The meaning of this field is determined by the active mailer transport
   *   service. For example, it could be a config entity ID or a DSN string.
   *
   * @return $this
   */
  public function setTransport(string $transport): static;

  /**
   * Gets the mailer transport that will be used.
   *
   * @return string
   *   Transport. The meaning of this field is determined by the active mailer
   *   transport service. For example, it could be a config entity ID or a DSN
   *   string.
   */
  public function getTransport(): string;

  /**
   * Gets the CSS for this email.
   *
   * @return string
   *   The CSS.
   */
  public function getCss(): string;

  /**
   * Gets the error message from sending the email.
   *
   * @return string
   *   Error message, or an empty string if there is no error.
   */
  public function getError(): string;

}
