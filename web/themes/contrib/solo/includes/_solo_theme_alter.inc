<?php

/**
 * @file
 * Solo Theme Alter.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\solo\Hook\SoloThemeAlterHooks;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Recursively detect field types within a form element.
 *
 * This function is now defined here (in alter.inc) since it's primarily used
 * for form alterations. Previously defined in preprocess.inc which caused
 * potential load order issues.
 *
 * @param array $element
 *   The form element to analyze.
 * @param array $types
 *   Array to store detected types and their counts (passed by reference).
 * @param int $depth
 *   Current recursion depth (internal use).
 * @param int $max_depth
 *   Maximum recursion depth to prevent infinite loops.
 */
function _solo_detect_container_field_types(array $element, array &$types, int $depth = 0, int $max_depth = 20): void {
  // Prevent infinite recursion on circular form references.
  if ($depth >= $max_depth) {
    return;
  }

  foreach (Element::children($element) as $key) {
    // Skip if not an array.
    if (!isset($element[$key]) || !is_array($element[$key])) {
      continue;
    }

    $child = $element[$key];

    // Check direct field type.
    if (isset($child['#type'])) {
      $type = $child['#type'];
      $types[$type] = ($types[$type] ?? 0) + 1;
    }

    // Check for fieldsets and their content.
    if ($key === 'wrapper' || (isset($child['#type']) && $child['#type'] === 'fieldset')) {
      // Mark that we have a fieldset.
      $types['fieldset'] = ($types['fieldset'] ?? 0) + 1;
    }

    // Check for min/max pattern (date range fields).
    if ($key === 'min' || $key === 'max') {
      $types['date-range-field'] = ($types['date-range-field'] ?? 0) + 1;
    }

    // Check if this element contains both min and max children.
    if (isset($child['min']) && isset($child['max'])) {
      $types['date-range-field'] = ($types['date-range-field'] ?? 0) + 1;
    }

    // Recurse into children with incremented depth.
    _solo_detect_container_field_types($child, $types, $depth + 1, $max_depth);
  }
}

/**
 * Custom validation handler to apply hidden content types to the search query.
 */
function solo_apply_hidden_content_types(array &$form, FormStateInterface $form_state): void {
  $hidden_content_types = $form['advanced']['hidden_content_types']['#value'] ?? [];
  if (!empty($hidden_content_types)) {
    $current_criteria = $form_state->getValue('type') ?? [];
    $updated_criteria = array_merge($current_criteria, $hidden_content_types);
    $form_state->setValue('type', $updated_criteria);
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
#[LegacyHook]
function solo_form_node_preview_form_select_alter(&$form, FormStateInterface $form_state, $form_id): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->formNodePreviewFormSelectAlter($form, $form_state, $form_id);
}

/**
 * Implements hook_form_FORM_ID_alter() for node_form.
 */
#[LegacyHook]
function solo_form_node_form_alter(array &$form, FormStateInterface $form_state, $form_id): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->formNodeFormAlter($form, $form_state, $form_id);
}

/**
 * Implements hook_theme_suggestions_node_edit_form_alter().
 *
 * Adds per-content-type template suggestions for node edit forms.
 * E.g., node_edit_form__article → node-edit-form--article.html.twig.
 */
#[LegacyHook]
function solo_theme_suggestions_node_edit_form_alter(array &$suggestions, array $variables): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->themeSuggestionsNodeEditFormAlter($suggestions, $variables);
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
#[LegacyHook]
function solo_form_comment_form_alter(&$form, FormStateInterface $form_state, $form_id): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->formCommentFormAlter($form, $form_state, $form_id);
}

/**
 * Implements hook_form_views_exposed_form_alter().
 *
 * Adds classes to Views exposed forms based on detected field types.
 * Automatically detects date range fields and applies appropriate classes.
 */
#[LegacyHook]
function solo_form_views_exposed_form_alter(&$form, FormStateInterface $form_state): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->formViewsExposedFormAlter($form, $form_state);
}

/**
 * Implements hook_form_FORM_ID_alter() for search_block_form.
 */
#[LegacyHook]
function solo_form_search_form_alter(&$form, FormStateInterface $form_state): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->formSearchFormAlter($form, $form_state);
}

/**
 * Implements hook_form_FORM_ID_alter() for search_block_form.
 */
#[LegacyHook]
function solo_form_search_block_form_alter(&$form, FormStateInterface $form_state): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->formSearchBlockFormAlter($form, $form_state);
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
#[LegacyHook]
function solo_library_info_alter(array &$libraries, string $extension): void {
  \Drupal::classResolver(SoloThemeAlterHooks::class)->libraryInfoAlter($libraries, $extension);
}
