<?php

namespace Drupal\solo\Hook;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Render\Element;
use Drupal\comment\CommentInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\solo\SoloIncludes;

/**
 * Hook implementations for solo.
 */
class SoloThemeAlterHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_node_preview_form_select_alter')]
  public static function formNodePreviewFormSelectAlter(&$form, FormStateInterface $form_state, $form_id): void {
    SoloIncludes::load();
    $form['backlink']['#options']['attributes']['class'][] = 'button';
    $form['backlink']['#options']['attributes']['class'][] = 'button--small';
    $form['backlink']['#options']['attributes']['class'][] = 'button--icon-back';
    $form['backlink']['#options']['attributes']['class'][] = 'button--primary';
    $form['view_mode']['#attributes']['class'][] = 'form-element--small';
  }

  /**
   * Implements hook_form_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public static function formNodeFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    SoloIncludes::load();
    // Set the theme for the form.
    $form['#theme'] = [
      'node_edit_form',
    ];
    // Define the 'advanced' and 'meta' containers.
    $form['advanced']['#type'] = 'container';
    $form['meta']['#type'] = 'container';
    // Set access for the 'meta' container.
    $form['meta']['#access'] = TRUE;
    // Add 'container-inline' class to 'changed' and 'author' wrappers.
    $form['meta']['changed']['#wrapper_attributes']['class'][] = 'container-inline';
    $form['meta']['author']['#wrapper_attributes']['class'][] = 'container-inline';
    // Define the 'revision_information' container and group it under 'meta'.
    $form['revision_information']['#type'] = 'container';
    $form['revision_information']['#group'] = 'meta';
  }

  /**
   * Implements hook_theme_suggestions_node_edit_form_alter().
   *
   * Adds per-content-type template suggestions for node edit forms.
   * E.g., node_edit_form__article → node-edit-form--article.html.twig.
   */
  #[Hook('theme_suggestions_node_edit_form_alter')]
  public static function themeSuggestionsNodeEditFormAlter(array &$suggestions, array $variables): void {
    SoloIncludes::load();
    $bundle = NULL;
    // Attempt to retrieve the bundle from the form's #node property (edit form).
    if (isset($variables['form']['#node']) && $variables['form']['#node'] instanceof NodeInterface) {
      $bundle = $variables['form']['#node']->bundle();
    }
    elseif ($route = \Drupal::routeMatch()) {
      // Check if route provides a node type object.
      if (($type = $route->getParameter('node_type')) && $type instanceof NodeType) {
        $bundle = $type->id();
      }
      elseif (($node = $route->getParameter('node')) && $node instanceof NodeInterface) {
        $bundle = $node->bundle();
      }
    }
    // Add the suggestion if bundle is known.
    if ($bundle) {
      $suggestions[] = 'node_edit_form__' . $bundle;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_comment_form_alter')]
  public function formCommentFormAlter(&$form, FormStateInterface $form_state, $form_id): void {
    SoloIncludes::load();
    $is_comment = $form_state->getFormObject()->getEntity();
    if ($is_comment instanceof CommentInterface) {
      $form['actions']['submit']['#value'] = $is_comment->hasParentComment() ? $this->t('Reply to comment') : $this->t('Post comment');
    }
  }

  /**
   * Implements hook_form_views_exposed_form_alter().
   *
   * Adds classes to Views exposed forms based on detected field types.
   * Automatically detects date range fields and applies appropriate classes.
   */
  #[Hook('form_views_exposed_form_alter')]
  public static function formViewsExposedFormAlter(&$form, FormStateInterface $form_state): void {
    SoloIncludes::load();
    $form['#attributes']['class'][] = 'form--inline';
    // Loop through ALL form elements - no hardcoded field names.
    foreach (Element::children($form) as $key) {
      if (!isset($form[$key]) || !is_array($form[$key])) {
        continue;
      }
      $element =& $form[$key];
      // Only process fieldsets and containers.
      if (!isset($element['#type']) || !in_array($element['#type'], [
        'fieldset',
        'container',
      ])) {
        continue;
      }
      // Detect field types within this element.
      $types = [];
      _solo_detect_container_field_types($element, $types);
      // Apply classes based on detected types.
      if (isset($types['date-range-field'])) {
        $element['#attributes']['class'][] = 'solo-date-range';
      }
      if (isset($types['fieldset'])) {
        $element['#attributes']['class'][] = 'solo-has-fieldset';
      }
      // Single date field (not a range).
      if (!isset($types['date-range-field']) && isset($types['date'])) {
        $element['#attributes']['class'][] = 'solo-single-date-container';
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for search_block_form.
   */
  #[Hook('form_search_form_alter')]
  public function formSearchFormAlter(&$form, FormStateInterface $form_state): void {
    SoloIncludes::load();
    // Remove the help link if it exists.
    if (isset($form['help_link'])) {
      unset($form['help_link']);
    }
    // Add a primary button class to forms with fewer than 3 actions.
    if (!empty($form['actions']['submit']) && isset($form['actions']) && count($form['actions']) <= 2) {
      $form['actions']['submit']['#attributes']['class'][] = 'button--primary';
    }
    // Add placeholder text to the keys input, if it exists.
    if (isset($form['keys'])) {
      $form['keys']['#attributes']['placeholder'] = $this->t('Search by keyword or phrase.');
    }
    // Add a primary button class to basic and advanced submit buttons if existed.
    if (isset($form['basic']['submit'])) {
      $form['basic']['submit']['#attributes']['class'][] = 'button--primary';
    }
    if (isset($form['advanced']['submit'])) {
      $form['advanced']['submit']['#attributes']['class'][] = 'button--primary';
    }
    // Retrieve theme settings for filters and content types.
    $disabled_filters = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('disable_search_filters'), fn() => theme_get_setting('disable_search_filters')) ?? [];
    $always_applied_content_types = array_filter(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('search_result_content_types'), fn() => theme_get_setting('search_result_content_types')) ?? []);
    // Check if all filters are disabled.
    if (in_array('disable_keywords', $disabled_filters, TRUE) && in_array('disable_types', $disabled_filters, TRUE) && in_array('disable_language', $disabled_filters, TRUE)) {
      // Remove the entire "Advanced" section.
      unset($form['advanced']);
      // No need to process further if all filters are disabled.
      return;
    }
    // Remove individual filters if specified.
    $filter_fieldsets = [
      'disable_keywords' => 'keywords-fieldset',
      'disable_language' => 'lang-fieldset',
    ];
    foreach ($filter_fieldsets as $filter_key => $fieldset_key) {
      if (isset($form['advanced'][$fieldset_key]) && in_array($filter_key, $disabled_filters, TRUE)) {
        unset($form['advanced'][$fieldset_key]);
      }
    }
    // Handle the "Types" filter specifically.
    if (isset($form['advanced']['types-fieldset']) && in_array('disable_types', $disabled_filters, TRUE)) {
      if (!empty($always_applied_content_types)) {
        // Hide the "Types" filter and add hidden content types.
        unset($form['advanced']['types-fieldset']);
        $form['advanced']['hidden_content_types'] = [
          '#type' => 'value',
          '#value' => $always_applied_content_types,
        ];
        // Add validation to apply these content types to the search query.
        $form['#validate'][] = 'solo_apply_hidden_content_types';
        SoloIncludes::addToFormBuildInfo($form_state);
      }
      else {
        // Remove the "Types" filter if no content types are specified.
        unset($form['advanced']['types-fieldset']);
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for search_block_form.
   */
  #[Hook('form_search_block_form_alter')]
  public function formSearchBlockFormAlter(&$form, FormStateInterface $form_state): void {
    SoloIncludes::load();
    // Add placeholder text to keys input.
    $form['keys']['#attributes']['placeholder'] = $this->t('Search by keyword or phrase.');
    // Add a class to the submit button.
    $form['actions']['submit']['#attributes']['class'][] = 'search-button-block';
    // DEBUG: Check what theme settings are returning.
    $raw_content_types = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('search_result_content_types'), fn() => theme_get_setting('search_result_content_types'));
    $content_types = array_filter($raw_content_types ?? []);
    // Attach the content types to drupalSettings.
    $form['#attached']['drupalSettings']['solo'] = [
      'searchContentTypes' => $content_types,
          // Simple static path.
      'searchBasePath' => '/search/node',
    ];
    // Attach the custom JS library.
    $form['#attached']['library'][] = 'solo/solo-search-settings';
  }

  /**
   * Implements hook_library_info_alter().
   *
   * Best-effort self-heal for the dynamic CSS/JS files that back the
   * "CSS Dynamic" / "JavaScript Dynamic" theme settings fields. The files
   * live in public://solo/{css,js}/... and are normally written by the
   * theme settings submit handler in theme-settings.php. If theme config
   * still has content but the on-disk file is missing — fresh deploy, a
   * sites/*\/files restore that omitted the dynamic directory, a config
   * import on an environment that never had the files — this hook rewrites
   * them from config so the library resolves cleanly.
   *
   * LIMITATION (theme-only fix): this hook fires when library discovery
   * rebuilds, not on every request. Rebuilds happen on drush cr, on a full
   * cache rebuild, when the library discovery cache expires, or on a
   * first-time discovery miss. It is NOT guaranteed to fire immediately
   * after `drush config:import` alone — cim only clears its own caches, not
   * library discovery. The standard deploy recipe `drush cim && drush cr`
   * covers it, because `cr` clears library discovery. A site that imports
   * config without running `drush cr` will keep serving the pre-import
   * library definition until the discovery cache is cleared or rebuilt, at
   * which point this hook fires and reconciles the on-disk files. For a
   * guaranteed post-cim-only regeneration, a small companion module with a
   * ConfigEvents::SAVE subscriber would be required — themes can't register
   * event subscribers (DrupalKernel loads services.yml from modules only).
   *
   * The library definitions remain static. Attachment is guarded in
   * preprocess_html() with file_exists(), which avoids caching transient
   * filesystem failures into library discovery state.
   */
  #[Hook('library_info_alter')]
  public static function libraryInfoAlter(array &$libraries, string $extension): void {
    SoloIncludes::load();
    if ($extension !== 'solo') {
      return;
    }
    // Pass 'solo' explicitly: hook_library_info_alter() can fire before
    // theme initialization, so theme_get_setting() without a $theme
    // argument may return defaults from the wrong theme.
    $css_dynamic = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('site_css_dynamic', 'solo'), fn() => theme_get_setting('site_css_dynamic', 'solo'));
    $js_dynamic = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('site_js_dynamic', 'solo'), fn() => theme_get_setting('site_js_dynamic', 'solo'));
    $css_uri = 'public://solo/css/solo-css-dynamic.css';
    $js_uri = 'public://solo/js/solo-js-dynamic.js';
    // Self-heal any drift between config and the on-disk files.
    $css_missing = !empty($css_dynamic) && !file_exists($css_uri);
    $js_missing = !empty($js_dynamic) && !file_exists($js_uri);
    if ($css_missing || $js_missing) {
      _solo_write_dynamic_assets($css_missing ? $css_dynamic : NULL, $js_missing ? $js_dynamic : NULL);
    }
  }

}
