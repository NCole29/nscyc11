<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The TransportUI attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class TransportUI extends Plugin {

  /**
   * Constructs a TransportUI attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param ?Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   Human-readable label of the plugin.
   * @param Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   Human-readable description of the plugin.
   * @param Drupal\Core\StringTranslation\TranslatableMarkup $warning
   *   Human-readable warning for the plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?TranslatableMarkup $warning = NULL,
  ) {}

}
