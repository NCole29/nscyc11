<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\symfony_mailer\Processor\EmailProcessorInterface;

/**
 * Defines the interface for Email Override plugins.
 */
interface OverrideInterface {

  /**
   * Sends an email from a message array.
   *
   * @param array $message
   *   The array to send from.
   * @param array $processor
   *   An email processor to use when sending.
   *
   * @return bool
   *   Whether successful.
   */
  public function send(array &$message, EmailProcessorInterface $processor): bool;

  /**
   * Imports Mailer Policy from legacy email settings.
   *
   * Implement this function if "import" is set in the Override definition.
   *
   * @param \Drupal\mailer_override\ImportHelperInterface $helper
   *   The import helper.
   */
  public function import(ImportHelperInterface $helper): void;

}
