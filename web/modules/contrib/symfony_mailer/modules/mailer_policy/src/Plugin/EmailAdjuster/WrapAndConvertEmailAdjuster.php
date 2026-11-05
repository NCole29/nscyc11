<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\EmailInterface;
use Html2Text\Html2Text;

/**
 * Defines the Wrap and convert Email Adjuster.
 */
#[EmailAdjuster(
  id: "mailer_wrap_and_convert",
  label: new TranslatableMarkup("Wrap and convert"),
  description: new TranslatableMarkup("Wraps the email and converts to plain text."),
  weight: 800,
)]
class WrapAndConvertEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  use AutowireTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param mixed ...$args
   *   Parent constructor arguments.
   *
   * @internal
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
    ...$args,
  ) {
    parent::__construct(...$args);
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    $orig_html = $email->getHtmlBody();
    $plain = $html = NULL;

    if ($orig_html && !$this->configuration['plain']) {
      $html = (string) $this->render($email, $orig_html, TRUE);
    }
    $email->setHtmlBody($html);

    if ($orig_plain = $email->getTextBody()) {
      // To wrap the plain text we need to convert to HTML to render the
      // template then convert back again.
      $plain = PlainTextOutput::renderFromHtml($this->render($email, Html::escape($orig_plain), FALSE));
    }
    elseif ($orig_html) {
      // Convert to plain text.
      // - Core uses MailFormatHelper::htmlToText(). However this is old code
      //   that's not actively maintained and there's no need for a
      //   Drupal-specific version of this generic code.
      // - Symfony Mailer library uses league/html-to-markdown. This is a bigger
      //   step away from what's been done in Drupal before, so we won't do
      //   that.
      // - Swiftmailer uses html2text/html2text, and that's what we do.
      $plain = (new Html2Text($this->render($email, $orig_html, FALSE)))->getText();
    }

    if ($plain) {
      $email->setTextBody($plain);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['plain'] = [
      '#title' => $this->t('Plain text'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['plain'] ?? NULL,
      '#description' => $this->t('Send as plain text only.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string {
    $titles = [
      'plain' => $this->t('Plain text'),
    ];
    foreach ($this->configuration as $id => $value) {
      if ($value) {
        $summary[] = $titles[$id];
      }
    }

    return implode(', ', $summary ?? []);
  }

  /**
   * Renders a body string using the wrapper template.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email being processed.
   * @param string $body
   *   The body string to wrap.
   * @param bool $is_html
   *   True if generating HTML output, false for plain text.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The wrapped body.
   */
  protected function render(EmailInterface $email, string $body, bool $is_html): MarkupInterface {
    $render = [
      '#theme' => 'email_wrap',
      '#email' => $email,
      '#body' => Markup::create($body),
      '#is_html' => $is_html,
    ];

    return $this->renderer->renderInIsolation($render);
  }

}
