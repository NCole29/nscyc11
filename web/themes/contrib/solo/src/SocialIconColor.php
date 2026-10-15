<?php

declare(strict_types=1);

namespace Drupal\solo;

/**
 * Rules for the social-icon colour theme setting.
 *
 * The value is printed straight into a style attribute by the SVG partials and
 * by _footer-menu.html.twig, so whatever is stored here has to be a CSS colour
 * VALUE. The mistake this class exists to catch is entering a bare custom
 * property NAME — "--my-brand" instead of "var(--my-brand)". CSS silently
 * discards the whole declaration in that case, so the icons fall back to the
 * inherited colour and render grey with nothing logged anywhere.
 *
 * That is the same fault commit 6827481 fixed in the templates' own fallback.
 * The templates are now correct; this closes the other half, where the value
 * comes from the site builder.
 *
 * The field carries an HTML5 pattern attribute, but that is client-side only
 * and is not a validation boundary: it is absent from a programmatic POST and
 * from a browser with validation disabled.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
final class SocialIconColor {

  /**
   * Matches a bare CSS custom property name, e.g. "--solo-fb".
   */
  private const BARE_CUSTOM_PROPERTY = '/^--[A-Za-z0-9_-]+$/';

  /**
   * Matches the values this setting accepts: #rgb, #rrggbb, or var(--name).
   */
  private const ACCEPTED = '/^(#[0-9A-Fa-f]{3}|#[0-9A-Fa-f]{6}|var\(\s*--[A-Za-z0-9_-]+\s*\))$/';

  /**
   * Whether the value is a custom property NAME rather than a value.
   *
   * @param string $value
   *   The submitted setting value.
   *
   * @return bool
   *   TRUE when the value looks like "--name" and so needs wrapping.
   */
  public static function isBareCustomProperty(string $value): bool {
    return (bool) preg_match(self::BARE_CUSTOM_PROPERTY, trim($value));
  }

  /**
   * Whether the value is one this setting accepts.
   *
   * An empty value is accepted: it means "use the theme default", which the
   * templates already handle with their own var() fallback.
   *
   * @param string $value
   *   The submitted setting value.
   *
   * @return bool
   *   TRUE when the value is empty, a hex colour, or a var() reference.
   */
  public static function isAcceptable(string $value): bool {
    $value = trim($value);
    return $value === '' || (bool) preg_match(self::ACCEPTED, $value);
  }

  /**
   * Wraps a bare custom property name so it becomes a usable CSS value.
   *
   * Offered so the error message can show the site builder exactly what to
   * type. Nothing calls this to mutate stored input: silently rewriting what
   * somebody typed hides the mistake rather than teaching it.
   *
   * @param string $value
   *   A bare custom property name.
   *
   * @return string
   *   The same name wrapped in var(), or the input unchanged if it was not a
   *   bare custom property name.
   */
  public static function suggest(string $value): string {
    $value = trim($value);
    return self::isBareCustomProperty($value) ? 'var(' . $value . ')' : $value;
  }

}
