<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\Attachment;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Inline Images Email Adjuster.
 */
#[EmailAdjuster(
  id: "mailer_inline_images",
  label: new TranslatableMarkup("Inline images"),
  description: new TranslatableMarkup("Convert image links to inline images."),
  weight: 900,
)]

class InlineImagesEmailAdjuster extends EmailAdjusterBase {

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    if ($body = $email->getHtmlBody()) {
      $dom = Html::load($body);

      foreach ($dom->getElementsByTagName('img') as $img) {
        $uri = $img->getAttribute('src');
        $email->attach(Attachment::fromPath($uri, isUri: TRUE));
      }
    }
  }

}
