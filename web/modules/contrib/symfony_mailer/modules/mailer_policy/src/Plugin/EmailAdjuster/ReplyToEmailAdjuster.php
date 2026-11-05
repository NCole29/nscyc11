<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;

/**
 * Defines the Reply-To Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_reply_to",
  label: new TranslatableMarkup("Reply-To"),
  description: new TranslatableMarkup("Sets the email reply-to header."),
)]
class ReplyToEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'reply-to';

}
