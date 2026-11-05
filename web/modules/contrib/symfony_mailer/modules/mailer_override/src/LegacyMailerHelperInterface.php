<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\symfony_mailer\LegacyConverterInterface;

/**
 * Provides the legacy mailer helper service.
 *
 * @deprecated in symfony_mailer:2.1.0 and is removed from symfony_mailer:3.0.0.
 *   Instead use Drupal\symfony_mailer\LegacyConverterInterface.
 * @see https://www.drupal.org/node/3613504
 */
interface LegacyMailerHelperInterface extends LegacyConverterInterface {}
