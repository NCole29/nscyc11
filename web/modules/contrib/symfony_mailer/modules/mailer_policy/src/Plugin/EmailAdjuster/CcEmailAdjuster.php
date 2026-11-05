<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;

/**
 * Defines the Cc Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_cc",
  label: new TranslatableMarkup("Cc"),
  description: new TranslatableMarkup("Sets the email cc header."),
)]
class CcEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'cc';

}
