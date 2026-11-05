<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Plugin\MailerOverride;

use Drupal\mailer_override\Attribute\Override;

/**
 * Defines the Override plug-in for user registration password module.
 */
#[Override(
  id: "user_registrationpassword",
  form_alter: [
    "user_admin_settings" => [
      "remove" => ["email_user_registrationpassword"],
    ],
  ],
)]
class UserRegistrationPasswordOverride extends UserOverride {}
