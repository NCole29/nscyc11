<?php

/**
 * @file
 * Documentation of Mailer Plus hooks.
 */

declare(strict_types=1);

use Drupal\symfony_mailer\EmailInterface;

/**
 * Acts on email in a phase.
 *
 * The phase names are defined in EmailInterface::PHASE_NAMES.
 *
 * @param \Drupal\symfony_mailer\EmailInterface $email
 *   The email.
 */
function hook_mailer_PHASE(EmailInterface $email): void {
  // hook_mailer_init():
  // - Add a class.
  // - Re-use an EmailAdjuster.
  $email->addProcessor(new CustomEmailProcessor());
  $config = ['message' => 'Unpopular user skipped'];
  Drupal::service(EmailAdjusterManagerInterface::class)->createInstance('email_skip_sending', $config)->init($email);

  // hook_mailer_init():
  $email->setTo('user@example.com');

  // hook_mailer_build():
  $body = $email->getBody();
  $body['extra'] = ['#markup' => 'Extra text'];
  $email->setBody($body);

  // hook_mailer_post_render():
  $email->setHtmlBody($email->getHtmlBody() . '<p><b>More</b> extra text</p>');

  // hook_mailer_post_send():
  $to = $email->getHeaders()->get('To')->getBodyAsString();
  \Drupal::messenger()->addMessage(t('Email sent to %to.', ['%to' => $to]));
}

/**
 * Acts on an email in a phase for a specific email type.
 *
 * The phase names are defined in EmailInterface::PHASE_NAMES.
 *
 * @param \Drupal\symfony_mailer\EmailInterface $email
 *   The email.
 */
function hook_mailer_TYPE_PHASE(EmailInterface $email): void {
}

/**
 * Acts on an email in a specific phase for a specific email type and sub-type.
 *
 * The phase names are defined in EmailInterface::PHASE_NAMES.
 *
 * @param \Drupal\symfony_mailer\EmailInterface $email
 *   The email.
 */
function hook_mailer_TYPE__SUBTYPE_PHASE(EmailInterface $email): void {
}

/**
 * Alters mailer plug-in definitions.
 *
 * @param array $mailers
 *   An associative array of all mailer definitions, keyed by the ID.
 */
function hook_symfony_mailer_info_alter(array &$mailers): void {
}
