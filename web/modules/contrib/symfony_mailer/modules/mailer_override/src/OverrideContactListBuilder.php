<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\contact\ContactFormListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mailer_policy\EmailAdjusterManagerInterface;
use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Defines a class to build a listing of contact form entities.
 *
 * @see \Drupal\contact\Entity\ContactForm
 */
class OverrideContactListBuilder extends ContactFormListBuilder {

  /**
   * Whether the contact override is enabled.
   */
  protected bool $enabled;

  /**
   * The email adjuster manager.
   */
  protected EmailAdjusterManagerInterface $adjusterManager;

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = parent::buildRow($entity);

    if ($entity->id() != 'personal' && $this->enabled()) {
      $config = MailerPolicy::loadInheritedConfig('contact.page.mail..' . $entity->id());
      if (isset($config['email_to'])) {
        $to = $this->adjusterManager->createInstance('email_to', $config['email_to'])->getSummary();
      }
      else {
        $to = '';
      }
      $row['recipients']['data']['#items'] = explode(',', $to);
    }

    return $row;
  }

  /**
   * Gets whether the contact override is enabled.
   *
   * @return bool
   *   Enabled status.
   */
  protected function enabled(): bool {
    if (!isset($this->enabled)) {
      $this->enabled = \Drupal::service(OverrideManagerInterface::class)->isEnabled('contact');
      $this->adjusterManager = \Drupal::service(EmailAdjusterManagerInterface::class);
    }
    return $this->enabled;
  }

}
