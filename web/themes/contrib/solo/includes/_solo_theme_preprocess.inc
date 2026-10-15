<?php

/**
 * @file
 * Theme preprocess functions.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\solo\Hook\SoloThemePreprocessHooks;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Controller\TitleResolver;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use Drupal\webform\Entity\Webform;
use Drupal\Core\Render\Element;

/**
 * Minifies trusted inline CSS without corrupting quoted strings.
 *
 * This is intentionally limited to whitespace/comment compaction for CSS that
 * trusted administrators enter into the theme settings "CSS Injector" field.
 * It mirrors the safe whitespace handling used by Drupal core's CSS optimizer
 * for quoted strings and selector syntax, but does not rewrite URLs because
 * this CSS is emitted inline.
 *
 * @param string $css
 *   Raw CSS from theme settings.
 *
 * @return string
 *   Compact CSS suitable for inline <style> output.
 */
function _solo_minify_inline_css(string $css): string {
  // Remove an initial @charset if present; it is invalid inside inline CSS.
  $css = preg_replace('/^@charset\s+[\'"](\S*?)\b[\'"];/i', '', $css);

  // Match comment blocks and quoted strings so comments can be removed without
  // touching string content.
  $comment = '/\*[^*]*\*+(?:[^/*][^*]*\*+)*/';
  $double_quot = '"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"';
  $single_quot = "'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'";

  $css = preg_replace(
    "<($double_quot|$single_quot)|$comment>Ss",
    '$1',
    $css
  );

  // Collapse whitespace in a CSS-aware way. Preserve spaces where selectors or
  // functional syntax require them, such as descendant combinators and media
  // query conditions.
  $css = preg_replace('<
    (' . $double_quot . '|' . $single_quot . ')
    | \s*([@{};,])\s*
    | \s+([\)])
    | ([\(:])\s+
  >xSs', '$1$2$3$4', $css);

  return trim((string) $css);
}

/**
 * Ensures the class attribute is an array before appending values.
 *
 * This defensive helper prevents "[] operator not supported for strings" errors
 * that occur when Drupal's Attribute objects convert class arrays to strings
 * during the rendering process.
 *
 * @param array $variables
 *   The variables array containing attributes.
 * @param string $attribute_path
 *   Attribute path (default: 'attributes').
 */
function _solo_ensure_class_array(array &$variables, string $attribute_path = 'attributes'): void {
  // Handle standard attributes path.
  if ($attribute_path === 'attributes') {
    if (!isset($variables['attributes']['class'])) {
      $variables['attributes']['class'] = [];
    }
    elseif (!is_array($variables['attributes']['class'])) {
      // Convert string to array if already processed by Attribute object.
      $variables['attributes']['class'] = [$variables['attributes']['class']];
    }
  }
  // Handle nested paths like 'label.#attributes'.
  elseif (str_contains($attribute_path, '.')) {
    $parts = explode('.', $attribute_path);
    $ref = &$variables;
    foreach ($parts as $part) {
      $part = trim($part, '#');
      if (!isset($ref[$part])) {
        $ref[$part] = [];
      }
      $ref = &$ref[$part];
    }
    if (!isset($ref['class'])) {
      $ref['class'] = [];
    }
    elseif (!is_array($ref['class'])) {
      $ref['class'] = [$ref['class']];
    }
  }
}

/**
 * Helper function to add node ID to body class.
 *
 * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
 *   The route match service.
 * @param array $variables
 *   The variables array (passed by reference).
 */
function _add_node_id_to_body_class(RouteMatchInterface $route_match, array &$variables): void {
  $node = $route_match->getParameter('node');
  if ($node && $node_id = $node->id()) {
    _solo_ensure_class_array($variables);
    $variables['attributes']['class'][] = 'path-node-' . $node_id;
  }
}

/**
 * Helper function to add taxonomy term to body class.
 *
 * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
 *   The route match service.
 * @param array $variables
 *   The variables array (passed by reference).
 */
function _add_taxonomy_term_to_body_class(RouteMatchInterface $route_match, array &$variables): void {
  $term = $route_match->getParameter('taxonomy_term');
  if ($term && $term->id()) {
    _solo_ensure_class_array($variables);
    $variables['attributes']['class'][] = 'path-taxonomy-term-' . $term->id();
  }
  if ($term && $term->bundle()) {
    _solo_ensure_class_array($variables);
    $variables['attributes']['class'][] = 'path-vocabulary-' . Html::cleanCssIdentifier($term->bundle());
  }
}

/**
 * Helper function.
 */
function _add_user_path_to_body_class(&$variables): void {
  $curr_path = \Drupal::service('path.current')->getPath();
  $curr_path = ltrim($curr_path, '/');
  $user_url = explode('/', $curr_path);
  if (isset($user_url[0], $user_url[1]) && ($user_url[0] == 'user')) {
    _solo_ensure_class_array($variables);
    $variables['attributes']['class'][] = 'path-user-' . $user_url[1];
  }
}

/**
 * Helper function to add view ID to body class.
 *
 * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
 *   The route match service.
 * @param array $variables
 *   The variables array (passed by reference).
 */
function _add_view_id_to_body_class(RouteMatchInterface $route_match, array &$variables): void {
  $view_id = $route_match->getParameter('view_id');
  if ($view_id) {
    _solo_ensure_class_array($variables);
    $variables['attributes']['class'][] = 'views-page path-view-' . Html::cleanCssIdentifier($view_id);
  }
}

/**
 * Helper function.
 */
function _add_admin_context_to_body_class(&$variables): void {
  /** @var \Drupal\Core\Routing\AdminContext $admin_context */
  $admin_context = \Drupal::service('router.admin_context');
  if ($admin_context->isAdminRoute()) {
    _solo_ensure_class_array($variables);
    $variables['attributes']['class'][] = 'admin-context';
  }
}

/**
 * Helper function.
 */
function _get_theme_setting_class($setting, $class_prefix) {
  $value = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting($setting), fn() => theme_get_setting($setting));
  return $value ? " {$class_prefix}-{$value}" : '';
}

/**
 * Helper function.
 */
function _get_html_classes() {
  $fs_size = _get_theme_setting_class('site_global_font_size', 'fs');
  $gap_size = _get_theme_setting_class('site_global_regions_gap', 'gap');

  return $fs_size . $gap_size;
}

/**
 * Helper function.
 */
function _get_enabled_skip_links() {
  $enabled_skip_links = [];

  if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('skip_header_content'), fn() => theme_get_setting('skip_header_content'))) {
    $enabled_skip_links['skip_header_content'] = TRUE;
  }

  if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('skip_navigation_content'), fn() => theme_get_setting('skip_navigation_content'))) {
    $enabled_skip_links['skip_navigation_content'] = TRUE;
  }

  if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('skip_main_content'), fn() => theme_get_setting('skip_main_content'))) {
    $enabled_skip_links['skip_main_content'] = TRUE;
  }

  if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('skip_footer_content'), fn() => theme_get_setting('skip_footer_content'))) {
    $enabled_skip_links['skip_footer_content'] = TRUE;
  }

  return $enabled_skip_links;
}

/**
 * Sanitizes a value for use as a CSS custom property (hex color).
 *
 * Drupal 11: only allow 6-digit hex to prevent XSS in inline styles.
 *
 * @param string|null $value
 *   Raw value from theme setting (e.g. color picker).
 *
 * @return string
 *   Sanitized hex (e.g. #ffffff) or empty string if invalid.
 */
function _solo_sanitize_hex_for_css($value): string {
  if ($value === NULL || $value === '') {
    return '';
  }
  $value = trim((string) $value);
  return preg_match('/^#[a-fA-F0-9]{6}$/', $value) ? $value : '';
}

/**
 * Attaches preloader variables, library, and cache to HTML preprocess.
 *
 * Loads library when preloader is enabled or force-show; sets markup vars only
 * when the preloader should show for this request.
 *
 * @param array &$variables
 *   The html preprocess variables (modified in place).
 * @param string $active_theme
 *   Active theme machine name.
 */
function _solo_preprocess_html_preloader(array &$variables, string $active_theme): void {
  $preloader = _solo_preloader_decision($active_theme);
  $variables['preloader_show'] = $preloader['show'];
  $variables['preloader_config'] = $preloader['config'];

  // Attach library and HTML state only when preloader is shown (no JS/CSS when disabled).
  if ($preloader['show']) {
    $variables['#attached']['library'][] = 'solo/solo-preloader';
    $variables['html_attributes']->addClass('solo-preloader-enabled');
    $variables['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'solo-preloader-active',
          'content' => 'true',
        ],
      ],
      'solo_preloader_flag',
    ];
    $variables['#cache']['tags'] = array_values(array_unique(array_merge(
      $variables['#cache']['tags'] ?? [],
      ['config:' . $active_theme . '.settings']
    )));
  }

  if ($preloader['show']) {
    // Attach preloader text font only when style is text and a font is chosen (avoids loading when preloader disabled or uses spinner/logo).
    $preloader_font = trim((string) ($preloader['config']['text_font'] ?? ''));
    $preloader_style = $preloader['config']['style'] ?? 'spinner';
    if ($preloader_font !== '' && in_array($preloader_style, ['text', 'logo_text'], TRUE)) {
      $variables['#attached']['library'][] = 'solo/solo-gfex' . substr($preloader_font, 4);
    }

    $preloader_style_parts = [];
    // Preloader background: merge background color + opacity into rgba (opacity applies to bg only).
    $preloader_bg_hex = _solo_sanitize_hex_for_css(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('settings_preloader___r_bg'), fn() => theme_get_setting('settings_preloader___r_bg')));
    $preloader_bg_opacity = min(100, max(0, (int) (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('preloader_bg_opacity'), fn() => theme_get_setting('preloader_bg_opacity')) ?? 100)));
    if ($preloader_bg_hex !== '') {
      $rgb = _solo_hex_to_rgb($preloader_bg_hex);
      $alpha = $preloader_bg_opacity / 100;
      $preloader_style_parts[] = '--solo-preloader-bg:rgba(' . $rgb['r'] . ',' . $rgb['g'] . ',' . $rgb['b'] . ',' . $alpha . ')';
    }
    else {
      $preloader_style_parts[] = '--solo-preloader-bg:transparent';
    }
    // Preloader text color: from theme setting (optional); not affected by background opacity.
    $r_tx = _solo_sanitize_hex_for_css(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('settings_preloader___r_tx'), fn() => theme_get_setting('settings_preloader___r_tx')));
    if ($r_tx !== '') {
      $preloader_style_parts[] = '--r-tx:' . $r_tx;
    }
    // Logo dimensions and opacity (CSS variables) when style uses a logo.
    if (in_array($preloader_style, ['logo', 'logo_text'], TRUE)) {
      $logo_w = (int) ($preloader['config']['logo_width'] ?? 160);
      $logo_h = (int) ($preloader['config']['logo_height'] ?? 80);
      $logo_op = min(100, max(0, (int) ($preloader['config']['logo_opacity'] ?? 100))) / 100;
      $preloader_style_parts[] = '--solo-preloader-logo-width:' . max(16, min(800, $logo_w)) . 'px';
      $preloader_style_parts[] = '--solo-preloader-logo-height:' . max(16, min(600, $logo_h)) . 'px';
      $preloader_style_parts[] = '--solo-preloader-logo-opacity:' . $logo_op;
    }
    // Text font size and line height (CSS variables) when style uses text. Line height derived from font size: unitless 1.2 so it scales (e.g. 50px font → effective 60px line height).
    if (in_array($preloader_style, ['text', 'logo_text'], TRUE)) {
      $text_size = (int) ($preloader['config']['text_font_size'] ?? 24);
      $text_size = max(12, min(100, $text_size));
      $preloader_style_parts[] = '--solo-preloader-text-font-size:' . $text_size . 'px';
      $preloader_style_parts[] = '--solo-preloader-text-line-height:1.2';
    }
    $variables['preloader_inline_style'] = implode(';', $preloader_style_parts);
    $variables['preloader_critical_css'] = 'html.solo-preloader-enabled #solo-preloader{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;background-color:var(--solo-preloader-bg,transparent);color:var(--r-tx,#333)}html.solo-preloader-enabled #solo-preloader .solo-preloader__inner{display:flex;flex-direction:column;align-items:center;gap:1rem}html.solo-preloader-enabled #solo-preloader .visually-hidden{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}';
    if (!empty($preloader['cache_contexts'])) {
      $variables['#cache']['contexts'] = array_values(array_unique(array_merge(
        $variables['#cache']['contexts'] ?? [],
        $preloader['cache_contexts']
      )));
    }
  }
}

/**
 * Attaches back-to-top variables, library, and cache to HTML preprocess.
 *
 * Respects: enable, admin routes, authenticated, hide on small screens.
 * Passes position, style, icon, scroll threshold. Cache: route, user.roles.
 *
 * @param array &$variables
 *   The html preprocess variables (modified in place).
 * @param string $active_theme
 *   Active theme machine name.
 */
function _solo_preprocess_html_back_to_top(array &$variables, string $active_theme): void {
  $enabled = (bool) DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_enabled'), fn() => theme_get_setting('back_to_top_enabled'));
  $variables['back_to_top_show'] = FALSE;
  $cache_contexts = [];

  if (!$enabled) {
    $variables['back_to_top_config'] = [];
    return;
  }

  $route_match = \Drupal::routeMatch();
  $route_name = $route_match ? $route_match->getRouteName() : '';
  // Pass an empty string so _solo_preloader_is_admin_route() resolves the path
  // from the request itself; getRawParameters() returns route parameters
  // (e.g. {node}), not the URL path.
  $disable_admin = (bool) DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_disable_admin_routes'), fn() => theme_get_setting('back_to_top_disable_admin_routes'));
  if ($disable_admin && _solo_preloader_is_admin_route($route_name, '')) {
    $cache_contexts[] = 'route';
    $variables['#cache']['contexts'] = array_values(array_unique(array_merge($variables['#cache']['contexts'] ?? [], $cache_contexts)));
    $variables['back_to_top_config'] = [];
    return;
  }

  $disable_authenticated = (bool) DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_disable_authenticated'), fn() => theme_get_setting('back_to_top_disable_authenticated'));
  if ($disable_authenticated && !\Drupal::currentUser()->isAnonymous()) {
    $cache_contexts[] = 'user.roles';
    $variables['back_to_top_show'] = FALSE;
    $variables['back_to_top_config'] = [];
    $variables['#cache']['contexts'] = array_values(array_unique(array_merge($variables['#cache']['contexts'] ?? [], $cache_contexts)));
    $variables['#cache']['tags'] = array_values(array_unique(array_merge($variables['#cache']['tags'] ?? [], ['config:' . $active_theme . '.settings'])));
    return;
  }

  $cache_contexts[] = 'route';
  if ($disable_authenticated) {
    $cache_contexts[] = 'user.roles';
  }

  $scroll_threshold = (int) DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_scroll_threshold'), fn() => theme_get_setting('back_to_top_scroll_threshold'));
  $scroll_threshold = max(200, min(800, $scroll_threshold));
  $position = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_position'), fn() => theme_get_setting('back_to_top_position')) ?: 'bottom-right';
  if (!in_array($position, ['bottom-right', 'bottom-left'], TRUE)) {
    $position = 'bottom-right';
  }
  $style = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_style'), fn() => theme_get_setting('back_to_top_style')) ?: 'solid';
  if (!in_array($style, ['solid', 'outline'], TRUE)) {
    $style = 'solid';
  }
  $icon = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_icon'), fn() => theme_get_setting('back_to_top_icon')) ?: 'arrow-up';
  if (!in_array($icon, ['arrow-up', 'chevron-up', 'arrow-minimal'], TRUE)) {
    $icon = 'arrow-up';
  }
  $hide_small_screens = (bool) DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('back_to_top_hide_small_screens'), fn() => theme_get_setting('back_to_top_hide_small_screens'));

  $variables['back_to_top_show'] = TRUE;
  $variables['back_to_top_config'] = [
    'scroll_threshold' => $scroll_threshold,
    'position' => $position,
    'style' => $style,
    'icon' => $icon,
    'hide_small_screens' => $hide_small_screens,
  ];

  $style_parts = [];
  $r_bg = _solo_sanitize_hex_for_css(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('settings_back_to_top___r_bg'), fn() => theme_get_setting('settings_back_to_top___r_bg')));
  $r_tx = _solo_sanitize_hex_for_css(DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('settings_back_to_top___r_tx'), fn() => theme_get_setting('settings_back_to_top___r_tx')));
  if ($r_bg !== '') {
    $style_parts[] = '--r-bg:' . $r_bg;
  }
  if ($r_tx !== '') {
    $style_parts[] = '--r-tx:' . $r_tx;
  }
  $variables['back_to_top_inline_style'] = implode(';', $style_parts);

  $variables['#attached']['library'][] = 'solo/solo-back-to-top';
  $existing_contexts = $variables['#cache']['contexts'] ?? [];
  $variables['#cache']['contexts'] = array_values(array_unique(array_merge($existing_contexts, $cache_contexts)));
  $existing_tags = $variables['#cache']['tags'] ?? [];
  $variables['#cache']['tags'] = array_values(array_unique(array_merge($existing_tags, ['config:' . $active_theme . '.settings'])));
}

/**
 * Implements hook_preprocess_HOOK() for HTML document templates.
 */
#[LegacyHook]
function solo_preprocess_html(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessHtml($variables);
}

/**
 * Helper function.
 */
function _get_site_regions() {
  return [
    'page_wrapper',
    'popup_login_block',
    'fixed_search_block',
    'header',
    'primary_sidebar_menu',
    'primary_menu',
    'welcome_text',
    'top_container',
    'top_first',
    'top_second',
    'top_third',
    'highlighted',
    'system_messages',
    'page_title',
    'breadcrumb',
    'main_container',
    'sidebar_first',
    'content',
    'sidebar_second',
    'bottom_container',
    'bottom_first',
    'bottom_second',
    'bottom_third',
    'bottom_fourth',
    'footer_container',
    'footer_first',
    'footer_second',
    'footer_third',
    'footer_menu',
    'credit_copyright',
  ];
}

/**
 * Helper function.
 */
function _get_css_classes() {
  $regions = _get_site_regions();
  $css_classes = [];
  foreach ($regions as $region) {
    $css_classes[] = "classes_{$region}";
  }
  return $css_classes;
}

/**
 * Helper function.
 */
function _get_region_alignment() {
  $regions = _get_site_regions();
  $region_alignment = [];
  foreach ($regions as $region) {
    $region_alignment[] = "align_{$region}";
  }
  return $region_alignment;
}

/**
 * Helper function.
 */
function _get_region_attributes(): array {
  return [
    '__r_bg'      => t('Region background'),
    '__r_tx'      => t('Region text'),
    '__r_h1'      => t('Headings (H1-H3)'),
    '__r_lk'      => t('Link text'),
    '__r_lk_h'    => t('Link text (hover)'),
    '__r_br'      => t('borders'),
    '__r_bg_fr'   => t('Form field background'),
    '__r_tx_lk'   => t('Menu link text'),
    '__r_tx_lk_h' => t('Menu link text (hover)'),
    '__r_bg_lk'   => t('Menu link background'),
    '__r_bg_lk_h' => t('Menu link background (hover)'),
    '__r_tx_bt'   => t('Button text'),
    '__r_tx_bt_h' => t('Button text (hover)'),
    '__r_bg_bt'   => t('Button background'),
    '__r_bg_bt_h' => t('Button background (hover)'),
  ];
}

/**
 * Helper function.
 */
function _get_settings_region() {
  $regions = _get_site_regions();
  $attributes = _get_region_attributes();
  $settings_region = [];
  foreach ($regions as $region) {
    foreach ($attributes as $attribute_key => $attribute_label) {
      $settings_region[] = "settings_{$region}_{$attribute_key}";
    }
  }
  return $settings_region;
}

/**
 * Convert hex color to RGB array.
 */
function _solo_hex_to_rgb(string $hex): array {
  $hex = trim($hex, '#');
  return [
    'r' => hexdec(substr($hex, 0, 2)),
    'g' => hexdec(substr($hex, 2, 2)),
    'b' => hexdec(substr($hex, 4, 2)),
  ];
}

/**
 * Helper function.
 */
function _get_region_attributes_for_region($region, $attributes) {
  $region_attr = [];

  foreach ($attributes as $key => $value) {
    if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting("settings_{$region}_{$key}"), fn() => theme_get_setting("settings_{$region}_{$key}"))) {
      $css_val = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting("settings_{$region}_{$key}"), fn() => theme_get_setting("settings_{$region}_{$key}"));
      $css_var = str_replace('_', '-', $key);
      // Un comment the below to use hsla colors.
      // $css_val = _solo_convert_hex_to_hsl($css_val);
      // $css_val = implode(', ', $css_val);
      // $css_val = "hsla({$css_val},1)";.
      $css_var = "{$css_var}:{$css_val}";
      $region_attr[] = $css_var;
    }
  }

  return $region_attr;
}

/**
 * Helper function.
 */
function _create_region_attributes(&$variables) {
  $site_regions = _get_site_regions();
  $attributes = _get_region_attributes();

  foreach ($site_regions as $region) {
    $variables["attributes_{$region}"] = new Attribute();
    $region_attr = _get_region_attributes_for_region($region, $attributes);

    if ($region_attr) {
      $variables["attributes_{$region}"]->setAttribute('style', implode(';', $region_attr));
    }
  }
}

/**
 * Helper function.
 */
function _get_theme_variables() {

  return [
    'misc' => [
      'opacity_page_wrapper',
      'site_global_breakpoints',
      'site_menu_breakpoints',
      'site_global_width',
      'site_css_injector',
      'site_name_google_font',
      'site_pagetitle_google_font',
      'site_font_google_special_on',
      'site_font_google_heading_on',
      'site_font_google_global_on',
      'site_font_google_local_on',
      'site_font_awesome_on',
      'site_load_w3css_on',
      'site_opacity_onscroll_on',
      'site_login_page_on',
      'site_pagetitle_animate_on',
      'site_pagetitle_font_size_s',
      'site_pagetitle_font_size_l',
      'site_name_font_size_s',
      'site_name_font_size_l',
      'site_name_animate_on',
      'site_breadcrumb_scroll',
      'site_breadcrumb_pagetitle_off',
      'site_inline_items_on',
      'site_regions_collapse_order',
      'site_regions_top_animate_border',
      'site_regions_bottom_animate_border',
      'site_regions_footer_animate_border',
      'site_regions_top_animate_hover',
      'site_regions_bottom_animate_hover',
      'site_regions_footer_animate_hover',
      'site_regions_top_rounded',
      'site_regions_main_rounded',
      'site_regions_bottom_rounded',
      'site_regions_footer_rounded',
      'site_regions_highlighted_disable',
      'site_regions_welcome_disable',
      'site_regions_welcome_width',
      'site_regions_top_disable',
      'site_regions_bottom_disable',
      'site_regions_footer_disable',
      'site_flip_header_menu',
      'site_mobile_layout_order',
      'primary_menu_border',
      'primary_menu_branding',
      'primary_menu_justify_content',
      'primary_sidebar_menu_border',
      'primary_sidebar_menu_branding',
      'site_regions_top_border',
      'site_regions_main_border',
      'site_regions_bottom_border',
      'site_regions_footer_border',
      'top_2col',
      'top_3col',
      'main_2col',
      'main_3col',
      'bottom_2col',
      'bottom_3col',
      'bottom_4col',
      'footer_2col',
      'footer_3col',
      'header_change_icons',
      'header_sitename_center',
      'header_sitename_center_small',
      'header_sitename_center_default',
      'header_logo_image_alt',
      'header_popup_login',
      'count_top',
      'count_main',
      'count_bottom',
      'count_footer',
      'theme_category',
      'enable_aria_bottom',
      'enable_aria_top',
      'enable_aria_footer',
      'enable_aria_welcome',
      'enable_aria_highlighted',
      'enable_block_title_visibility',
      'disable_block_title_visibility',
    ],
    'social' => [
      'sm_icon_size',
      'sm_icon_colors',
      'sm_show_icons',
      'sm_icon_snapchat',
      'sm_icon_tiktok',
      'sm_icon_threads',
      'sm_icon_whatsapp',
      'sm_icon_telegram',
      'sm_icon_reddit',
      'sm_icon_facebook',
      'sm_icon_twitter',
      'sm_icon_bluesky',
      'sm_icon_pinterest',
      'sm_icon_linkedin',
      'sm_icon_instagram',
      'sm_icon_youtube',
      'sm_icon_drupal',
      'sm_icon_email',
      'sm_icon_rss',
      'sm_icon_mastodon',
      'footer_copyright',
      'footer_link',
      'footer_link_label',
      'footer_link_text',
      'footer_use_formatted_text',
    ],
    'css_classes' => _get_css_classes(),
    'region_alignment' => _get_region_alignment(),
    'settings_region' => _get_settings_region(),
  ];
}

/**
 * Returns the width class for a given node, if custom node widths are enabled.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The node to check.
 *
 * @return string|null
 *   The width class string, or NULL if not defined or not supported.
 */
function _get_node_width_class(NodeInterface $node): ?string {
  $cache = &drupal_static(__FUNCTION__, []);
  $nid = $node->id();
  if (array_key_exists($nid, $cache)) {
    return $cache[$nid];
  }
  $cache[$nid] = NULL;
  if (\Drupal::moduleHandler()->moduleExists('solo_utilities') &&
      \Drupal::entityTypeManager()->hasDefinition('node_width')) {
    $items = \Drupal::entityTypeManager()->getStorage('node_width')
      ->loadByProperties(['node_id' => $nid]);
    if (!empty($items)) {
      $cache[$nid] = reset($items)->get('width_class')->value;
    }
  }
  return $cache[$nid];
}

/**
 * Implements hook_preprocess_page() for page templates.
 */
#[LegacyHook]
function solo_preprocess_page(&$variables) {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessPage($variables);
}

/**
 * Implements hook_preprocess_HOOK() for node.html.twig.
 */
#[LegacyHook]
function solo_preprocess_node(array &$variables) {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessNode($variables);
}

/**
 * Helper function: Adds node titles to the breadcrumb.
 */
function _add_node_breadcrumb(RouteMatchInterface $route_match, array &$breadcrumb): void {
  if ($route_match->getRouteName() === 'entity.node.canonical') {
    $node = $route_match->getParameter('node');
    if ($node instanceof NodeInterface) {
      $label = $node->label();
      if (is_array($label)) {
        $label = reset($label);
      }
      $breadcrumb[] = ['text' => Html::decodeEntities((string) $label)];
    }
  }
}

/**
 * Helper function: Adds taxonomy term titles to the breadcrumb.
 */
function _add_taxonomy_breadcrumb(RouteMatchInterface $route_match, array &$breadcrumb): void {
  if ($route_match->getRouteName() === 'entity.taxonomy_term.canonical') {
    $term = $route_match->getParameter('taxonomy_term');
    if ($term instanceof TermInterface) {
      $label = $term->label();
      if (is_array($label)) {
        $label = reset($label);
      }
      $breadcrumb[] = ['text' => Html::decodeEntities((string) $label)];
    }
  }
}

/**
 * Helper function: Adds user profile names to the breadcrumb.
 */
function _add_user_breadcrumb(RouteMatchInterface $route_match, array &$breadcrumb): void {
  if ($route_match->getRouteName() === 'entity.user.canonical') {
    $user = $route_match->getParameter('user');
    if ($user instanceof UserInterface) {
      $display_name = $user->getDisplayName();
      if (is_array($display_name)) {
        $display_name = reset($display_name);
      }
      $breadcrumb[] = ['text' => Html::decodeEntities((string) $display_name)];
    }
  }
}

/**
 * Helper function: Adds webform titles to the breadcrumb.
 */
function _add_webform_breadcrumb(RouteMatchInterface $route_match, array &$breadcrumb): void {
  if ($route_match->getRouteName() === 'entity.webform.canonical') {
    $webform = $route_match->getParameter('webform');
    if ($webform instanceof Webform) {
      $label = $webform->label();
      if (is_array($label)) {
        $label = reset($label);
      }
      $breadcrumb[] = ['text' => Html::decodeEntities((string) $label)];
    }
  }
}

/**
 * Helper function: Adds views titles to the breadcrumb.
 */
function _add_view_breadcrumb(RouteMatchInterface $route_match, array &$breadcrumb, TitleResolver $title_resolver, array $body_classes) {
  // Get the route and check if the page is generated by a View.
  $route = $route_match->getRouteObject();
  $view_id = $route ? $route->getDefault('view_id') : NULL;

  // Ensure it's a Views Page by checking both `view_id` and 'views-page' class.
  $is_views_page = $view_id || (!empty($body_classes) && in_array('views-page', $body_classes));

  if (!$is_views_page) {
    return;
  }

  // Attempt to get the title from the title resolver.
  $request = \Drupal::request();
  $title = $title_resolver->getTitle($request, $route);

  // Handle ViewsRenderPipelineMarkup objects.
  if ($title instanceof ViewsRenderPipelineMarkup) {
    $title = $title->__toString();
  }

  // If the title is a render array, extract #markup.
  if (is_array($title) && isset($title['#markup'])) {
    $title = $title['#markup'];
  }

  // Ensure the title is a valid string.
  if (!is_string($title) || empty($title)) {
    return;
  }

  // Add the title to the breadcrumb.
  $breadcrumb[] = ['text' => Html::decodeEntities($title)];
}

/**
 * Implements hook_preprocess_HOOK() for breadcrumb.html.twig.
 */
#[LegacyHook]
function solo_preprocess_breadcrumb(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessBreadcrumb($variables);
}

/**
 * Implements hook_preprocess_field().
 */
#[LegacyHook]
function solo_preprocess_field(&$variables, $hook) {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessField($variables, $hook);
}

/**
 * Implements hook_preprocess_HOOK().
 */
#[LegacyHook]
function solo_preprocess_field__node__created(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessFieldNodeCreated($variables);
}

/**
 * Implements hook_preprocess_HOOK() for setting classes.
 */
#[LegacyHook]
function solo_preprocess_filter_caption(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessFilterCaption($variables);
}

/**
 * Implements hook_preprocess_search_result().
 */
#[LegacyHook]
function solo_preprocess_search_result(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessSearchResult($variables);
}

/**
 * Implements hook_preprocess_HOOK() for comment templates.
 *
 * Provides configurable comment display based on theme settings.
 * Supports accessible heading-first layout and customizable visibility
 * of comment metadata elements.
 */
#[LegacyHook]
function solo_preprocess_comment(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessComment($variables);
}

/**
 * Implements hook_preprocess_HOOK() for block.html.twig.
 */
#[LegacyHook]
function solo_preprocess_block(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessBlock($variables);
}

/**
 * Helper function.
 */
function _load_block($id): ?EntityInterface {
  return \Drupal::entityTypeManager()
    ->getStorage('block')
    ->load($id);
}

/**
 * Helper function.
 */
function _process_system_menu_block(&$variables, $block): void {
  if ($variables['base_plugin_id'] !== 'system_menu_block') {
    return;
  }

  $region = $block->getRegion();
  $variables['content']['#attributes']['region'] = $region;
}

/**
 * Helper function.
 */
function _set_logo_default(&$variables): void {
  // Get the machine name of the current default theme.
  $default_theme = \Drupal::config('system.theme')->get('default');
  // Get the machine name of the active theme.
  $active_theme_name = \Drupal::theme()->getActiveTheme()->getName();
  // Get the 'logo.use_default' configuration from the 'solo' theme.
  $logo_default = \Drupal::config('solo.settings')->get('logo.use_default');

  // If both active and default themes are 'solo' and 'logo.use_default' is 1.
  if ($default_theme === 'solo' && $active_theme_name === 'solo' && $logo_default == 1) {
    $variables['logo_default'] = 1;
  }
  else {
    // Initialize $logo_default to 0.
    $variables['logo_default'] = 0;
  }
}

/**
 * Helper function.
 */
function _set_logo_alt(&$variables): void {
  $variables['logo_alt'] = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.3.0', fn() => \Drupal::service(ThemeSettingsProvider::class)->getSetting('header_logo_image_alt'), fn() => theme_get_setting('header_logo_image_alt')) ?: 'Home';
}

/**
 * Helper function.
 */
function _process_system_branding_block(&$variables): void {
  // Ensure this function only processes system branding blocks.
  if ($variables['base_plugin_id'] !== 'system_branding_block') {
    return;
  }
}

/**
 * Implements hook_preprocess_menu().
 */
#[LegacyHook]
function solo_preprocess_menu__primary_menu(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessMenuPrimaryMenu($variables);
}

/**
 * Implements hook_preprocess_menu().
 */
#[LegacyHook]
function solo_preprocess_menu__primary_sidebar_menu(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessMenuPrimarySidebarMenu($variables);
}

/**
 * Implements hook_preprocess_menu().
 */
#[LegacyHook]
function solo_preprocess_menu__account(&$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessMenuAccount($variables);
}

/**
 * Implements hook_preprocess_menu().
 */
#[LegacyHook]
function solo_preprocess_menu(&$variables) {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessMenu($variables);
}

/**
 * Implements hook_preprocess_HOOK() for form-element.html.twig.
 */
#[LegacyHook]
function solo_preprocess_form_element(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessFormElement($variables);
}

/**
 * Implements hook_preprocess_HOOK() for form-element-label.html.twig.
 */
#[LegacyHook]
function solo_preprocess_form_element_label(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessFormElementLabel($variables);
}

/**
 * Implements hook_preprocess_HOOK() for input.html.twig.
 */
#[LegacyHook]
function solo_preprocess_input(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessInput($variables);
}

/**
 * Implements hook_preprocess_HOOK() for menu-local-task.html.twig.
 */
#[LegacyHook]
function solo_preprocess_menu_local_task(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessMenuLocalTask($variables);
}

/**
 * Implements hook_preprocess_HOOK() for field--comment.html.twig.
 */
#[LegacyHook]
function solo_preprocess_field__comment(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessFieldComment($variables);
}

/**
 * Sets the site name for maintenance pages.
 *
 * @param array $variables
 *   The variables array.
 */
function _set_site_name(array &$variables): void {
  // By default, site_name is set to Drupal if no db connection is available
  // or during site installation. Setting site_name to an empty string makes
  // the site and update pages look cleaner.
  // @see template_preprocess_maintenance_page
  if (!($variables['db_is_active'] ?? FALSE)) {
    $variables['site_name'] = '';
  }
}

/**
 * Adds maintenance page library.
 *
 * @param array $variables
 *   The variables array.
 */
function _maintenance_library(array &$variables): void {
  // Solo has custom styling for the maintenance page.
  $variables['#attached']['library'][] = 'solo/templates/layout/maintenance_page';
}

/**
 * Implements hook_preprocess_HOOK() for maintenance-page.html.twig.
 */
#[LegacyHook]
function solo_preprocess_maintenance_page(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessMaintenancePage($variables);
}

/**
 * Implements hook_preprocess_HOOK() for views-view-table.html.twig.
 */
#[LegacyHook]
function solo_preprocess_views_view_table(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessViewsViewTable($variables);
}

/**
 * Implements hook_preprocess_HOOK() for fieldset.html.twig.
 */
#[LegacyHook]
function solo_preprocess_fieldset(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessFieldset($variables);
}

/**
 * Implements hook_preprocess_HOOK() for form.html.twig.
 */
#[LegacyHook]
function solo_preprocess_form(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessForm($variables);
}

/**
 * Marks entity reference fields for AJAX behaviors.
 *
 * @param array $elements
 *   The form elements array.
 */
function _solo_mark_entity_reference_fields(array &$elements): void {
  foreach (Element::children($elements) as $key) {
    if (isset($elements[$key]['#type'])) {
      // Detect entity reference fields.
      if ($elements[$key]['#type'] === 'entity_autocomplete') {
        $elements[$key]['#attributes']['data-entity-type'] = $elements[$key]['#target_type'] ?? '';
        $target_bundles = $elements[$key]['#selection_settings']['target_bundles'] ?? [];
        $elements[$key]['#attributes']['data-bundle'] = is_array($target_bundles) ? implode(',', $target_bundles) : '';

        // Ensure wrapper_attributes class is an array.
        if (!isset($elements[$key]['#wrapper_attributes']['class'])) {
          $elements[$key]['#wrapper_attributes']['class'] = [];
        }
        elseif (!is_array($elements[$key]['#wrapper_attributes']['class'])) {
          $elements[$key]['#wrapper_attributes']['class'] = [$elements[$key]['#wrapper_attributes']['class']];
        }
        $elements[$key]['#wrapper_attributes']['class'][] = 'entity-ref-wrapper';
      }

      // Detect select lists that are entity references.
      if ($elements[$key]['#type'] === 'select' && isset($elements[$key]['#entity_type'])) {
        // Ensure wrapper_attributes class is an array.
        if (!isset($elements[$key]['#wrapper_attributes']['class'])) {
          $elements[$key]['#wrapper_attributes']['class'] = [];
        }
        elseif (!is_array($elements[$key]['#wrapper_attributes']['class'])) {
          $elements[$key]['#wrapper_attributes']['class'] = [$elements[$key]['#wrapper_attributes']['class']];
        }
        $elements[$key]['#wrapper_attributes']['class'][] = 'entity-select-wrapper';
      }
    }
    // Recurse into children.
    if (is_array($elements[$key])) {
      _solo_mark_entity_reference_fields($elements[$key]);
    }
  }
}

/**
 * Implements hook_preprocess_HOOK() for container.html.twig.
 */
#[LegacyHook]
function solo_preprocess_container(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessContainer($variables);
}

/**
 * Check if container has min/max fields.
 *
 * @param array $element
 *   The element to check.
 *
 * @return bool
 *   TRUE if has min/max fields, FALSE otherwise.
 */
function _solo_container_has_min_max(array $element): bool {
  $has_min = FALSE;
  $has_max = FALSE;

  foreach (Element::children($element) as $key) {
    // Direct check.
    if ($key === 'min') {
      $has_min = TRUE;
    }
    if ($key === 'max') {
      $has_max = TRUE;
    }

    // Check nested elements.
    if (isset($element[$key]) && is_array($element[$key])) {
      foreach (Element::children($element[$key]) as $child_key) {
        if ($child_key === 'min') {
          $has_min = TRUE;
        }
        elseif (isset($element[$key][$child_key]['#name'])) {
          $child_name = (string) $element[$key][$child_key]['#name'];
          if (str_contains($child_name, '[min]')) {
            $has_min = TRUE;
          }
          if (str_contains($child_name, '[max]')) {
            $has_max = TRUE;
          }
        }

        if ($child_key === 'max') {
          $has_max = TRUE;
        }
      }
    }
  }

  return $has_min && $has_max;
}

/**
 * Implements hook_preprocess_HOOK() for field-multiple-value-form.html.twig.
 */
#[LegacyHook]
function solo_preprocess_field_multiple_value_form(array &$variables): void {
  \Drupal::classResolver(SoloThemePreprocessHooks::class)->preprocessFieldMultipleValueForm($variables);
}
