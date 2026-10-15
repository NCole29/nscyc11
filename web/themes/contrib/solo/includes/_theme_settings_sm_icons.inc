<?php

/**
 * @file
 * Solo Theme.
 *
 * Filename:     solo.theme
 * Website:      http://www.flashwebcenter.com
 * Description:  template
 * Author:       Alaa Haddad http://www.alaahaddad.com.
 */

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;

/**
 * The theme settings.
 */

$form["solo_settings"]["settings_social_icons"] = [
  "#type" => "details",
  "#title" => t("<strong class='purple'>Social Media Links</strong> Settings - Solo Theme"),
  "#collapsible" => TRUE,
  "#collapsed" => TRUE,
  "#description" => t("Configure social media icon display, styling, and platform links."),
];

// Create vertical tabs container.
$form["solo_settings"]["settings_social_icons"]["social_media_tabs"] = [
  "#type" => "vertical_tabs",
  "#default_tab" => "edit-general-settings",
];

// General Settings Tab.
$form["solo_settings"]["settings_social_icons"]["general_settings"] = [
  "#type" => "details",
  "#title" => t("General Settings"),
  "#group" => "social_media_tabs",
  "#weight" => -10,
  "#id" => "edit-general-settings",
];

$form["solo_settings"]["settings_social_icons"]["general_settings"]["sm_icon_size"] = [
  "#type" => "select",
  "#title" => t("The size of the social media icons can be altered from here."),
  "#default_value" => DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting("sm_icon_size"), fn() => theme_get_setting("sm_icon_size")),
  "#options" => [
    "sm-icons-small" => t("Small - Default"),
    "sm-icons-medium"  => t("Medium icons"),
    "sm-icons-large"   => t("Large icons"),
  ],
];

$form["solo_settings"]["settings_social_icons"]["general_settings"]["sm_icon_colors"] = [
  "#type" => "textfield",
  // Long enough for a var() reference; 7 only fitted a hex colour, which made
  // the "enter var(--name)" advice impossible to follow.
  "#maxlength" => 40,
  "#size" => 20,
  "#title" => t("Change Social Media Default Colors"),
  "#description" => t("A hex colour such as #1877f2, or a custom property reference such as var(--solo-fb). A bare property name like --solo-fb will not work: browsers ignore it and the icons stay grey. Leave empty to use each network's default colour."),
  "#default_value" => DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting("sm_icon_colors"), fn() => theme_get_setting("sm_icon_colors")),
  "#attributes" => [
    // Mirrors Drupal\solo\SocialIconColor::ACCEPTED. This is a convenience
    // only; the server-side check in solo_theme_settings_validate() is the
    // boundary that actually holds.
    "pattern" => "^(#[0-9A-Fa-f]{3}|#[0-9A-Fa-f]{6}|var\\(\\s*--[A-Za-z0-9_-]+\\s*\\))$",
  ],
  "#wrapper_attributes" => [
    "data-drupal-selector" => "solo-color-picker",
  ],
];

$form["solo_settings"]["settings_social_icons"]["general_settings"]["sm_show_icons"] = [
  "#type" => "checkbox",
  "#title" => t("Show Social Icons"),
  "#default_value" => DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting("sm_show_icons"), fn() => theme_get_setting("sm_show_icons")),
  "#description" => t("Select this option to display the Social Icon. Deselect it to conceal it."),
];

// Social Platform Links Tab.
$form["solo_settings"]["settings_social_icons"]["platform_links"] = [
  "#type" => "details",
  "#title" => t("Social Platform Links"),
  "#group" => "social_media_tabs",
  "#weight" => -8,
];

$social_icons = [
  "facebook" => t("Facebook"),
  "twitter" => t("Formerly (Twitter) X"),
  "bluesky" => t("Bluesky"),
  "pinterest" => t("Pinterest"),
  "linkedin" => t("Linkedin"),
  "instagram" => t("Instagram"),
  "youtube" => t("Youtube"),
  "mastodon" => t("Mastodon"),
  "reddit" => t("Reddit"),
  "whatsapp" => t("WhatsApp"),
  "telegram" => t("Telegram"),
  "snapchat" => t("Snapchat"),
  "threads" => t("Threads"),
  "tiktok" => t("TikTok"),
  "drupal" => t("Drupal"),
  "rss" => t("RSS"),
  "email" => t("Email"),
];

foreach ($social_icons as $key => $value) {
  $form["solo_settings"]["settings_social_icons"]["platform_links"]["sm_icon_" . $key] = [
  // Check if the key is 'email'.
  // '#type' => $key === 'email' ? 'email' : 'textfield',.
    "#type" => "textfield",
    "#title" => $value,
    "#default_value" => DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting("sm_icon_" . $key), fn() => theme_get_setting("sm_icon_" . $key)),
  ];
}
