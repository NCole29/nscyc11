<?php

/**
 * @file
 * nsctheme_settings.inc
 * Add Club accounts and subtheme colors to the Appearance > theme > settings form.
 * The _nsctheme_form_element method is in the Solo theme.
 */

/**
 * The theme settings.
 */

  $prefix = "nsctheme";
  $label = "NSC theme"; 

  $form["nsctheme_settings"]["{$prefix}"] = [
    "#type" => "details",
    "#title" => t("<strong class='info'>@label</strong> Settings - NSCtheme Solo SubTheme", ["@label" => $label]),
    "#collapsible" => TRUE,
    "#collapsed" => TRUE,
    "#description" => t("Add RWGPS/Strava links. Configure colors for paragraph design elements (callouts, cards). "),
  ];

  // Create vertical tabs container.
  $form["nsctheme_settings"]["{$prefix}"]["custom_tabs"] = [
    "#type" => "vertical_tabs",
    "#default_tab" => "edit-club-accounts",
  ];

  // Club accounts Tab.
  $form['nsctheme_settings']["{$prefix}"]['club_accounts'] = [
    '#type' => 'details',
    '#title' => t("RWGPS link"),
    "#group" => "custom_tabs",
    "#weight" => -10,
    "#id" => "edit-club-accounts",
  ];
  $form['nsctheme_settings']["{$prefix}"]['club_accounts']["{$prefix}_rwgps_url"] = [
    '#type' => 'textfield',
    '#title' => t('RWGPS Club Account'),
    '#default_value' => theme_get_setting("{$prefix}_rwgps_url"),
  ];

  // Cancellation color Tab.
  $form["nsctheme_settings"]["{$prefix}"]["cancel_color_tab"] = [
    "#type" => "details",
    "#title" => t("Cancellation Color"),
    "#group" => "custom_tabs",
    "#weight" => 0,
    '#attributes' => [
      'class' => ['solo-color-panel'],
      'data-solo-panel' => 'colors',
    ],
    "#description" => t('Ride cancellation alerts are displayed in the <strong>Highlighted region</strong>. That region appears above under Page Wrapper and a background color should be set there.'), 
  ];

  // Paragraphs (callout, card) colors Tab.
  $form["nsctheme_settings"]["{$prefix}"]["color_settings_tab"] = [
    "#type" => "details",
    "#title" => t("Design Components"),
    "#group" => "custom_tabs",
    "#weight" => 0,
    '#attributes' => [
      'class' => ['solo-color-panel'],
      'data-solo-panel' => 'colors',
    ],
    "#description" => t('Design components (Drupal paragraphs types) may be added when selecting Type of page ="Page with section layouts and design components.
    <p>A callout is a textbox with a left border that sets the information apart from the main text.</p> 
    <p>A card contains a title, image, and text and is generally used to display a collection of items (e.g., board members, events, etc).</p>
    Callouts and cards default to a white background unless users select a shaded background which employs the colors specified here'), 
  ];
  $attributes =  [
    'callout_br' => t('Callout border'),
    'callout_bt_bg' => t('Callout button background'),
    'callout_bt_tx' => t('Callout button text'),
    'callout_bg' => t('Callout background, shaded'),
    'card_bg'    => t('Card background, shaded'),
  ];
  foreach ($attributes as $attribute_key => $attribute_label) {
    $settings_region[] = "{$prefix}_{$attribute_key}";
  }

  foreach ($attributes as $attribute_key => $attribute_label) {
    $form["nsctheme_settings"]["{$prefix}"]["color_settings_tab"]["{$prefix}_{$attribute_key}"] = _nsctheme_form_element($prefix, $label, $attribute_key, $attribute_label);
  }

