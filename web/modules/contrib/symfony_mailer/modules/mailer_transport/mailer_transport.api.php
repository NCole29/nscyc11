<?php

/**
 * @file
 * Documentation of Mailer Transport hooks.
 */

declare(strict_types=1);

/**
 * Alters mailer transport plug-in definitions.
 *
 * @param array $mailer_transports
 *   An associative array of all mailer transport definitions, keyed by the ID.
 */
function hook_mailer_transport_info_alter(array &$mailer_transports): void {
}
