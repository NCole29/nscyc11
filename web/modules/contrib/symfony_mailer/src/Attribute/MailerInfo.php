<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Attribute for component mailer information.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MailerInfo extends AttributeBase {

  /**
   * Constructor for Mailer attribute.
   *
   * @param string $base_tag
   *   The tag prefix that indicates what emails this mailer builds.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the mailer. Leave blank to derive from an
   *   entity type or module matching the ID.
   * @param array $sub_defs
   *   Array of sub-types. The array key is the sub-type value and the value is
   *   either the human-readable label, or another array of the same form.
   * @param ?string $metadata_key
   *   Email parameters can be used as metadata to group emails and configure
   *   them separately. For example the body of the contact message auto-reply
   *   can be different for each contact form. The value is a parameter key
   *   whose value is a config entity.
   * @param string[] $required_config
   *   Array of required configuration IDs.
   * @param array $token_types
   *   Array of token types that can be used in email fields.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $variables
   *   Array of variables that can be used in email fields. The key is the
   *   variable name and the value is a human-readable description.
   */
  public function __construct(
    public readonly string $base_tag,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly array $sub_defs = [],
    public readonly ?string $metadata_key = NULL,
    public readonly array $required_config = [],
    public readonly array $token_types = [],
    public readonly array $variables = [],
  ) {}

}
