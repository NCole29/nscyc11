<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of email adjusters.
 */
class AdjusterPluginCollection extends DefaultLazyPluginCollection {

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id): void {
    $configuration = $this->configurations[$instance_id];
    $this->set($instance_id, $this->manager->createInstance($instance_id, $configuration));
  }

  /**
   * Provides uasort() callback to sort plugins.
   */
  public function sortHelper($aID, $bID): int {
    $a = $this->get($aID);
    $b = $this->get($bID);
    return strnatcasecmp((string) $a->getPluginDefinition()['label'], (string) $b->getPluginDefinition()['label']);
  }

}
