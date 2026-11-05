<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\EmailInterface;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Defines the Inline CSS Email Adjuster.
 */
#[EmailAdjuster(
  id: "mailer_inline_css",
  label: new TranslatableMarkup("Inline CSS"),
  description: new TranslatableMarkup("Add inline CSS."),
  weight: 900,
)]
class InlineCssEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    // Only inline CSS if we have an html body.
    if (($html_body = $email->getHtmlBody()) && ($css = $email->getCss())) {
      $cssInliner = new CssToInlineStyles();
      $email->setHtmlBody($cssInliner->convert($html_body, $css));
    }
  }

}
