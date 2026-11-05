<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Core\Entity\EntityListBuilderInterface;

/**
 * Defines an interface to build Mailer Policy entity listings.
 */
interface MailerPolicyListBuilderInterface extends EntityListBuilderInterface {

  /**
   * Overrides the entities to display.
   *
   * @param string[] $entity_ids
   *   An array of entity IDs.
   * @param string $skip
   *   Number of levels to skip when displaying the tag.
   *
   * @return $this
   */
  public function overrideEntities(array $entity_ids, int $skip = 0): self;

  /**
   * Hides columns in the output.
   *
   * @param string[] $columns
   *   The columns to hide.
   *
   * @return $this
   */
  public function hideColumns(array $columns): self;

}
