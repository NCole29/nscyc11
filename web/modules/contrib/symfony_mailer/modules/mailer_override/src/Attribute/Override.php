<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The Override attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Override extends Plugin {

  /**
   * Constructor for Override attribute.
   *
   * @param string $id
   *   The plugin ID. This is a tag prefix that indicates what emails this
   *   plugin builds. All emails that are built have a tag that starts with
   *   this prefix.
   * @param string[] $override
   *   Array of email IDs to override. Defaults to a single-value array
   *   containing the plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $warning
   *   Human-readable warning for overriding.
   * @param string[] $config
   *   Array of config IDs to load when the override is enabled. Matching
   *   mailer policy is included automatically so should not be listed here.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $import
   *   Human-readable name of config to import.
   * @param string $import_warning
   *   Human-readable warning for importing.
   * @param array $config_overrides
   *   Array of config overrides. As required by
   *   ConfigFactoryOverrideInterface::loadOverrides().
   * @param array $form_alter
   *   Array of form alter information. The array key is the form ID, or '*'
   *   for the add/edit form of the corresponding config entity. The value is
   *   an array with the following allowed keys.
   *   - remove: Array of fields to remove from the form.
   *   - default: Array with key as the field name, and value as the field
   *     default value.
   *   - type: Show policy for the specified type.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?array $override = [],
    public readonly ?TranslatableMarkup $warning = NULL,
    public readonly array $config = [],
    public readonly ?TranslatableMarkup $import = NULL,
    public readonly ?TranslatableMarkup $import_warning = NULL,
    public readonly array $config_overrides = [],
    public readonly array $form_alter = [],
  ) {}

}
