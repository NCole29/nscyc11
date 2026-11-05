<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides the mailer helper service.
 */
interface PolicyHelperInterface {

  /**
   * Renders an element that lists relevant policy.
   *
   * The element is designed for insertion into a config entity or settings
   * form.
   *
   * @param string $tag
   *   Base tag of the policies to show.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   Config entity being edited.
   *
   * @return array
   *   The render array.
   */
  public function renderPolicy(string $tag, ?ConfigEntityInterface $entity = NULL): array;

}
