<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;

/**
 * Defines the From Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_from",
  label: new TranslatableMarkup("From"),
  description: new TranslatableMarkup("Sets the email from header."),
)]
class FromEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'from';

}
