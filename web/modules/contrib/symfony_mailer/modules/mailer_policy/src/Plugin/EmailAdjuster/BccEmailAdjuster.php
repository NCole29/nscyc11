<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;

/**
 * Defines the Bcc Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_bcc",
  label: new TranslatableMarkup("Bcc"),
  description: new TranslatableMarkup("Sets the email bcc header."),
)]
class BccEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'bcc';

}
