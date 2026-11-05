<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the To Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_to",
  label: new TranslatableMarkup("To"),
  description: new TranslatableMarkup("Sets the email to header."),
)]
class ToEmailAdjuster extends AddressAdjusterBase {

  /**
   * The name of the associated header.
   */
  protected const NAME = 'to';

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    parent::build($email);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
  }

}
