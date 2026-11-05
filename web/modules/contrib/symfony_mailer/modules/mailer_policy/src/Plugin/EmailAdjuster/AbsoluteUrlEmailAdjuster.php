<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the URL to absolute Email Adjuster.
 */
#[EmailAdjuster(
  id: "mailer_url_to_absolute",
  label: new TranslatableMarkup("URL to absolute"),
  description: new TranslatableMarkup("Convert URLs to absolute."),
  weight: 700,
)]
class AbsoluteUrlEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    $email->setHtmlBody(Html::transformRootRelativeUrlsToAbsolute($email->getHtmlBody(), \Drupal::request()->getSchemeAndHttpHost()));
  }

}
