<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Component;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\update\UpdateManagerInterface;

/**
 * Defines the Mailer plug-in for update module.
 *
 * Replaces _update_cron_notify().
 *
 * The notification address is configured using Mailer Policy for
 * UpdateMailer. Set a dummy value in update.settings to force the update
 * module to send an email. NB UpdateMailer ignores the passed 'To'
 * address so the dummy value will never be used.
 */
#[MailerInfo(
  base_tag: "update",
  sub_defs: ["status_notify" => new TranslatableMarkup("Available updates")],
  required_config: ["email_subject", "email_body", "email_to"],
  variables: [
    'site_name' => new TranslatableMarkup("Site name"),
    'update_status' => new TranslatableMarkup("Link to update status page"),
    'update_settings' => new TranslatableMarkup("Link to update settings page"),
    'messages' => new TranslatableMarkup("Update messages"),
    'update_manager' => new TranslatableMarkup("Link to update manager page"),
  ],
)]
class UpdateMailer extends ComponentMailerBase implements UpdateMailerInterface {

  /**
   * {@inheritdoc}
   */
  public function notify(): bool {
    return $this->newEmail('status_notify')->send();
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    if (empty($email->getTo())) {
      throw new SkipMailException('No update notification address configured.');
    }

    $notify_all = (\Drupal::config('update.settings')->get('notification.threshold') == 'all');
    \Drupal::moduleHandler()->loadInclude('update', 'install');
    if (function_exists('update_requirements')) {
      $requirements = update_requirements('runtime');
    }
    else {
      $requirements = \Drupal::moduleHandler()->invoke('update', 'runtime_requirements');
    }

    $messages = [];
    foreach (['core', 'contrib'] as $report_type) {
      $status = $requirements["update_$report_type"];
      if (isset($status['severity'])) {
        if ($status['severity'] == RequirementSeverity::Error || ($notify_all && $status['reason'] == UpdateManagerInterface::NOT_CURRENT)) {
          $messages[] = _update_message_text($report_type, $status['reason']);
        }
      }
    }

    $site_name = \Drupal::config('system.site')->get('name');
    $email->setVariable('site_name', $site_name)
      ->setVariable('update_status', Url::fromRoute('update.status')->toString())
      ->setVariable('update_settings', Url::fromRoute('update.settings')->toString())
      ->setVariable('messages', $messages);
  }

}
