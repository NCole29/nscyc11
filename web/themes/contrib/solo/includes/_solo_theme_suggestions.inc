<?php

/**
 * @file
 * Theme Sugestions.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\solo\Hook\SoloThemeSuggestionsHooks;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
#[LegacyHook]
function solo_theme_suggestions_form_alter(array &$suggestions, array $variables): void {
  \Drupal::classResolver(SoloThemeSuggestionsHooks::class)->themeSuggestionsFormAlter($suggestions, $variables);
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for form templates.
 */
#[LegacyHook]
function solo_theme_suggestions_block_alter(array &$suggestions, array $variables): void {
  \Drupal::classResolver(SoloThemeSuggestionsHooks::class)->themeSuggestionsBlockAlter($suggestions, $variables);
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for menu.
 */
#[LegacyHook]
function solo_theme_suggestions_menu_alter(array &$suggestions, array $variables): void {
  \Drupal::classResolver(SoloThemeSuggestionsHooks::class)->themeSuggestionsMenuAlter($suggestions, $variables);
}

/**
 * Helper function.
 */
function _add_node_suggestions(RouteMatchInterface $route_match, array &$suggestions): void {
  if ($route_match->getRouteName() === 'entity.node.canonical') {
    $node_bundle = $route_match->getParameter('node')->bundle();
    array_splice($suggestions, 1, 0, 'page__' . $node_bundle);
  }
}

/**
 * Helper function.
 */
function _add_taxonomy_suggestions(RouteMatchInterface $route_match, array &$suggestions): void {
  if ($route_match->getRouteName() === 'entity.taxonomy_term.canonical') {
    $taxonomy_bundle = $route_match->getParameter('taxonomy_term')->bundle();
    array_splice($suggestions, 1, 0, 'page__taxonomy__' . $taxonomy_bundle);
  }
}

/**
 * Helper function.
 */
function _add_view_suggestions(RouteMatchInterface $route_match, array &$suggestions): void {
  if ($view_name = $route_match->getRouteObject()->getDefault('view_id')) {
    $suggestions[] = 'page__' . $view_name;
  }
}

/**
 * Implements hook_theme_suggestions_page_alter().
 */
#[LegacyHook]
function solo_theme_suggestions_page_alter(array &$suggestions, array $variables): void {
  \Drupal::classResolver(SoloThemeSuggestionsHooks::class)->themeSuggestionsPageAlter($suggestions, $variables);
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
#[LegacyHook]
function solo_theme_suggestions_user_alter(array &$suggestions, array $variables) {
  \Drupal::classResolver(SoloThemeSuggestionsHooks::class)->themeSuggestionsUserAlter($suggestions, $variables);
}
