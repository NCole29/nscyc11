<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor\automatic;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;
use Drupal\symfony_mailer\Processor\ReplaceableProcessorInterface;
use Drupal\symfony_mailer\MailerLookupInterface;

/**
 * Email processor for token and variable replacement.
 */
class ReplacementEmailProcessor implements EmailProcessorInterface {

  use EmailProcessorTrait;
  use AutowireTrait;

  /**
   * Whether tokens have been replaced in the HTML body.
   */
  protected bool $replacedHtml = FALSE;

  /**
   * ReplacementEmailProcessor constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\symfony_mailer\MailerLookupInterface $mailerLookup
   *   The mailer lookup service.
   *
   * @internal
   */
  public function __construct(
    protected readonly RendererInterface $renderer,
    protected readonly Token $token,
    protected readonly MailerLookupInterface $mailerLookup,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $variables = $email->getVariables();
    $data = $email->getParam('token_data') ?? [];
    $options = $email->getParam('token_options') ?? [];

    if ($token_types = $this->mailerLookup->getTagDefinition($email->getTag())['token_types'] ?? []) {
      $data += array_fill_keys($token_types, NULL);
    }

    if (!$variables && !$data) {
      return;
    }

    foreach ($email->getProcessors() as $processor) {
      if ($processor instanceof ReplaceableProcessorInterface) {
        $plain = TRUE;
        $value = $processor->getValue($email, $plain, $this->replacedHtml);

        if ($variables) {
          if ($plain) {
            $value = Html::escape($value);
          }

          $render = [
            '#type' => 'inline_template',
            '#template' => $value,
            '#context' => $variables,
          ];
          $value = $this->renderer->renderInIsolation($render);

          if ($plain) {
            $value = PlainTextOutput::renderFromHtml($value);
          }
        }

        if ($data) {
          foreach ($data as $kk => &$vv) {
            if (is_null($vv)) {
              // NULL value means to copy the corresponding parameter.
              $vv = $email->getParam($kk);
            }
          }

          if ($plain) {
            $value = $this->token->replacePlain($value, $data, $options);
          }
          else {
            $value = $this->token->replace($value, $data, $options);
            $this->replacedHtml = TRUE;
          }
        }

        $processor->setValue($email, $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    $data = $email->getParam('token_data') ?? [];
    $options = $email->getParam('token_options') ?? [];

    // The body may have been set by a template, in which case we need to
    // replace tokens in it now.
    if ($data && !$this->replacedHtml && $body = $email->getHtmlBody()) {
      $email->setHtmlBody($this->token->replace($body, $data, $options));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    if ($phase == EmailInterface::PHASE_BUILD) {
      return 900;
    }
    return EmailInterface::DEFAULT_WEIGHT;
  }

}
