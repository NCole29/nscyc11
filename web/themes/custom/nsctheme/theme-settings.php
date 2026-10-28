<?php

/**
 * @file
 * Add items to Appearance > Settings > nsctheme form.
 *
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function nsctheme_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {

  require_once __DIR__ . '/includes/nsctheme_settings.inc';
}


/**
 * Modified from Solo helper function _generate_form_element() to shorten "settings" names.
 */
function _nsctheme_form_element($prefix, $label, $attribute_key, $attribute_label) {
  return [
    '#type' => 'textfield',
    '#maxlength' => 7,
    '#size' => 10,
    '#title' => t("(@attribute_label)", ['@attribute_label' => $attribute_label]),
    '#default_value' => theme_get_setting("{$prefix}_{$attribute_key}"),
    '#attributes' => [
      'pattern' => '^#[a-fA-F0-9]{6}',
    ],
    '#wrapper_attributes' => [
      'data-drupal-selector' => 'solo-color-picker',
    ],
  ];
}
/**
 * Update ckeditor.css if Custom CSS has ":root" colors
 *
 */
/*
function _nsctheme_theme_settings_submit(&$form, FormStateInterface $form_state) {
  // Get the theme whose settings are being altered.
  $theme = $form_state->getBuildInfo()['args'][0];
  $config = \Drupal::configFactory()->getEditable("$theme.settings");

  $css = \Drupal::config('css_editor.theme.nsctheme')->get('css');

  if ($css) {
    $start = strpos($css,':root');
    $end   = strpos($css,'}',$start);

    if ($start and $end) {
      $root  = substr($css, $start, $end);

      // Read /themes/nsctheme/css/ckeditor4.base & ckeditor5.base and append after ":root" to write new css files
      $themepath = \Drupal::service('extension.list.theme')->getPath('nsctheme');
      $base5file = $themepath . '/css/ckeditor5.base';
      $css5file = $themepath . '/css/ckeditor5.css';

      $data5 = $root . "\r" . file_get_contents($base5file);

      file_put_contents($css5file, $data5, FILE_USE_INCLUDE_PATH | LOCK_EX);
    }
  }
}
*/