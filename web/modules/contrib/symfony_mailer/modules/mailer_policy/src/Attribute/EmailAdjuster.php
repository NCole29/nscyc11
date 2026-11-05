<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The EmailAdjuster attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EmailAdjuster extends Plugin {

  /**
   * Constructor for EmailAdjuster attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param ?Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   Human-readable label of the plugin.
   * @param Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   Human-readable description of the plugin.
   * @param ?int|int[] $weight
   *   The plugin weight. The array key is the phase (EmailInterface::PHASE_*)
   *   and the value is the weight for that phase. Lower weights are executed
   *   first. The attribute may specify a single integer that applies to all
   *   phases, and this will be automatically converted to an array.
   *
   * @internal
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly mixed $weight = NULL,
  ) {}

}
