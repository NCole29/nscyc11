<?php

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;

/**
 * Defines the Sender Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_sender",
  label: new TranslatableMarkup("Sender"),
  description: new TranslatableMarkup("Sets the email sender header."),
)]
class SenderEmailAdjuster extends AddressAdjusterBase {

  protected const NAME = 'sender';

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    // Add more info about the sender address.
    $form['addresses'][0]['warning_default'] = [
      '#prefix' => '<p>',
      '#markup' => new TranslatableMarkup('The sender defaults to the site email address configured in "Basic site settings". You should only use this adjuster if you need a different value.'),
      '#suffix' => '</p>',
    ];
    // Text taken from Drupal site config form.
    $form['addresses'][0]['warning_domain'] = [
      '#prefix' => '<p>',
      '#markup' => new TranslatableMarkup("Use an address ending in your site's domain to help prevent this email being flagged as spam."),
      '#suffix' => '</p>',
    ];

    // Only one sender is allowed.
    unset($form['add']);
    return $form;
  }

}
