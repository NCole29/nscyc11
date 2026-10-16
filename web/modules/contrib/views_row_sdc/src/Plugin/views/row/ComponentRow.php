<?php

namespace Drupal\views_row_sdc\Plugin\views\row;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsRow;
use Drupal\views\Plugin\views\row\RowPluginBase;

/**
 * Views Row plugin to display each row in a Single Directory Component.
 */
#[ViewsRow(
  id: "sdc_component",
  title: new TranslatableMarkup("Single Directory Component"),
  help: new TranslatableMarkup("Display each row of the result in a Single Directory Component"),
  display_types: ["normal"]
)]
class ComponentRow extends RowPluginBase {

  /**
   * Does the row plugin support to add fields to its output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $componentManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['component_id'] = ['default' => ''];
    $options['props'] = ['default' => []];
    $options['slots'] = ['default' => []];
    return $options;
  }

  /**
   * Creates the menu path for a standard AJAX form given the form state.
   *
   * Note: In future we should use `ViewsFormHelperTrait`, which provides this
   * function, but it's new in 11.4, which makes backwards compatibility
   * challenging. When 11.5 releases we should drop 11.3 support, implement the
   * trait, and delete this function.
   *
   * @return \Drupal\Core\Url
   *   The URL object pointing to the form URL.
   */
  protected function buildFormUrl(FormStateInterface $formState): Url {
    $ajax = !$formState->get('ajax') ? 'nojs' : 'ajax';
    $name = $formState->get('view')->id();
    $formKey = $formState->get('form_key');
    $displayId = $formState->get('display_id');

    $formKey = str_replace('-', '_', $formKey);
    $routeName = "views_ui.form_$formKey";
    $routeParameters = [
      'js' => $ajax,
      'view' => $name,
      'display_id' => $displayId,
    ];
    $url = Url::fromRoute($routeName, $routeParameters);
    if ($type = $formState->get('type')) {
      $url->setRouteParameter('type', $type);
    }
    if ($id = $formState->get('id')) {
      $url->setRouteParameter('id', $id);
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Get the previously saved component.
    $component_id = $this->options['component_id'] ?? '';

    if (!is_null($form_state->get('view'))) {
      $url = $this->buildFormUrl($form_state);
    }

    $form['component_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Component'),
      '#options' => $this->getComponentList(),
      '#default_value' => $component_id,
      '#required' => TRUE,
      '#description' => $this->t('Select the component to render for each row.'),
      '#empty_option' => $this->t('- Select component -'),
      '#ajax' => [
        'callback' => '::updateSdcProps',
        'event' => 'change',
        'wrapper' => 'sdc-wrapper',
        'url' => $url ?? NULL,
      ],
    ];

    // Check if user input, probably from AJAX.
    $input = $form_state->getUserInput();
    if (isset($input['row_options'])) {
      $component_id = $input['row_options']['component_id'];
    }

    $form['sdc_wrapper'] = [
      '#prefix' => '<div id="sdc-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($component_id) {
      $definition = $this->componentManager->getDefinition($component_id);
      $props = $definition['props']['properties'] ?? [];
      $slots = $definition['slots'] ?? [];

      if (!empty($props)) {
        $form['sdc_wrapper']['props'] = [
          '#type' => 'details',
          '#title' => $this->t('Props'),
          '#open' => TRUE,
        ];

        foreach ($props as $prop_name => $info) {
          $form['sdc_wrapper']['props'][$prop_name] = [
            '#type' => 'select',
            '#title' => $this->t('@prop', ['@prop' => $info['title'] ?? $prop_name]),
            '#description' => $info['description'] ?? '',
            '#options' => $this->getFieldOptions($info),
            '#default_value' => $this->getDefaultValue($this->options['props'][$prop_name] ?? NULL),
            '#empty_option' => $this->t('- None -'),
          ];
        }
      }

      if (!empty($slots)) {
        $form['sdc_wrapper']['slots'] = [
          '#type' => 'details',
          '#title' => $this->t('Slots'),
          '#open' => TRUE,
        ];

        foreach ($slots as $slot_name => $info) {
          $form['sdc_wrapper']['slots'][$slot_name] = [
            '#type' => 'select',
            '#title' => $this->t('@slot', ['@slot' => $info['title'] ?? $slot_name]),
            '#description' => $info['description'] ?? '',
            '#options' => $this->getFieldOptions($info),
            '#default_value' => $this->getDefaultValue($this->options['slots'][$slot_name] ?? NULL),
            '#empty_option' => $this->t('- None -'),
          ];
        }
      }
    }
  }

  /**
   * Get a list of single directory components, filtering out unwanted ones.
   *
   * @return array
   *   Array of components with machine names as keys.
   */
  protected function getComponentList(): array {
    $components = [];
    // Build list of components, using descriptive names where available.
    foreach ($this->componentManager->getDefinitions() as $id => $definition) {
      // Only add to component selector if `noUi` not set.
      if (
        (!array_key_exists('noUi', $definition) || $definition['noUi'] != TRUE) &&
        (!array_key_exists('status', $definition) || in_array($definition['status'], [
          'stable',
          'deprecated',
          'experimental',
        ]))
      ) {
        $components[$id] = $definition['name'] ? $this->t('%name (%id)', ['%name' => $definition['name'], '%id' => $id]) : $id;
      }
    }
    // Sort preserving keys.
    uasort($components, fn($a, $b) => strnatcmp($a, $b));

    return $components;
  }

  /**
   * Return default value for stored field.
   *
   * @param array $detail
   *   The stored prop or slot detail.
   *
   * @return string
   *   The #default_value for the option field.
   */
  protected function getDefaultValue(array|NULL $detail): string {
    if (isset($detail['field']) && $detail['field'] != '') {
      return 'field:' . $detail['field'];
    }
    if (isset($detail['static']) && $detail['static'] != '') {
      return 'static:' . $detail['static'];
    }
    return '';
  }

  /**
   * Return the AJAX form elements.
   */
  public function updateSdcProps(array &$form, FormStateInterface $form_state): array {
    return $form['sdc_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    // Get the form date - all values will be under "row_options".
    $values = $form_state->getValue(['row_options']);

    // Rearrange props so they are stored in the format we need.
    $component_id = $values['component_id'];

    if ($component_id) {
      // Get the component definition so we can store property types.
      $definition = $this->componentManager->getDefinition($component_id);
      $props = $definition['props']['properties'] ?? [];
      $slots = $definition['slots'] ?? [];

      // First delete the sdc_wrapper, as it will fail the schema check.
      $form_state->unsetValue(['row_options', 'sdc_wrapper']);
      // Rearrange props.
      foreach ($props as $prop_name => $info) {
        $this->setStateValue($form_state, 'props', $prop_name, $info, $values['sdc_wrapper']['props'][$prop_name]);
      }

      // Rearrange slots.
      foreach ($slots as $slot_name => $info) {
        $this->setStateValue($form_state, 'slots', $slot_name, $info, $values['sdc_wrapper']['slots'][$slot_name]);
      }
    }

  }

  /**
   * Store the prop or slot config, ensuring that no selection handled.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $props_or_slots
   *   Either "props" or "slots".
   * @param string $name
   *   Name of the prop or slot.
   * @param array $info
   *   Info from SDC schema.
   * @param string $selection
   *   Selected value from select control.
   */
  protected function setStateValue(FormStateInterface $form_state, string $props_or_slots, string $name, array $info, string $selection): void {
    // If nothing selected, save empty field.
    if (empty($selection)) {
      $form_state->setValue(['row_options', $props_or_slots, $name, 'field'], '');
      if ($props_or_slots == 'props') {
        $form_state->setValue(['row_options', $props_or_slots, $name, 'static'], '');
        $this->setStatePropInfo($form_state, $name, $info);
      }
      return;
    }

    // Split selection into 'method' (static or field) and value.
    [$method, $value] = explode(':', $selection);
    // Instead of just storing field name, store field name and type.
    if ($method) {
      $form_state->setValue(['row_options', $props_or_slots, $name, 'field'], $method == 'field' ? $value : '');
      if ($props_or_slots == 'props') {
        $form_state->setValue(['row_options', $props_or_slots, $name, 'static'], $method == 'static' ? $value : '');
        $this->setStatePropInfo($form_state, $name, $info);
      }
    }
  }

  /**
   * Copy values from SDC property schema to prop config.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $name
   *   Prop name.
   * @param array $info
   *   Info from SDC schema.
   */
  protected function setStatePropInfo(FormStateInterface $form_state, string $name, array $info): void {
    $form_state->setValue(['row_options', 'props', $name, 'type'], $info['type']);
    if (array_key_exists('enum', $info)) {
      $form_state->setValue(['row_options', 'props', $name, 'enum'], $info['enum']);
    }
  }

  /**
   * Helper function to get view fields to map to component props or slots.
   *
   * @return array
   *   The list of component properties.
   */
  protected function getFieldOptions(array $prop_slot): array {
    // Fill options list. Prefix field IDs with 'field:' to distinguish from
    // static values.
    $options = [];
    foreach ($this->view->display_handler->getFieldLabels() as $id => $label) {
      $options["field:$id"] = $label;
    }

    // If field is a boolean, add static "true" and "false".
    if (array_key_exists('type', $prop_slot) && $prop_slot['type'] == 'boolean') {
      $bool_options = [
        'static:1' => $this->t('True'),
        'static:0' => $this->t('False'),
      ];
      return array_merge($bool_options, $options);
    }

    // If field is a enum, add static entries for each enum value.
    if (array_key_exists('enum', $prop_slot)) {
      $enum_options = [];
      foreach ($prop_slot['enum'] as $enum_item) {
        if (array_key_exists('meta:enum', $prop_slot) && array_key_exists($enum_item, $prop_slot['meta:enum'])) {
          $enum_options["static:$enum_item"] = $prop_slot['meta:enum'][$enum_item];
        }
        else {
          $enum_options["static:$enum_item"] = $enum_item;
        }
      }
      return array_merge($enum_options, $options);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render($row): array {

    if (empty($this->options['component_id'])) {
      // No component selected, so return empty render array.
      return [];
    }

    if (!isset($this->view)) {
      // View not set, so return the component with no props or slots defined.
      return [
        '#type' => 'component',
        '#component' => $this->options['component_id'],
        '#props' => [],
        '#slots' => [],
      ];
    }

    $row_index = $this->view->row_index;

    // Props.
    $props = [];
    foreach ($this->options['props'] ?? [] as $prop_name => $prop_info) {
      $field_id = $prop_info['field'] ?? NULL;
      $static = $prop_info['static'] ?? NULL;
      $type = $prop_info['type'];
      if (!is_null($field_id) && $field_id != '') {
        // Get the rendered value for the mapped views field.
        $value = $this->view->style_plugin->getField($row_index, $field_id);
        $raw_value = $this->view->field[$field_id]->getValue($row);
      }
      elseif (!is_null($static) && $static != '') {
        // If static, just pass to output.
        $value = $static;
        $raw_value = $static;
      }
      else {
        // The prop is not mapped to a field nor a static value: skip it so
        // the component falls back to its declared default. Passing an empty
        // string breaks class-typed props (e.g. Drupal Attribute objects) and
        // enum-constrained props under SDC schema validation.
        continue;
      }
      // Type specific processing.
      $props[$prop_name] = match($type) {
        'boolean' => $this->booleanProp($value),
        'integer' => $this->integerProp($value),
        'string' => $this->stringProp($prop_info, $value, $raw_value),
        'object' => $this->objectProp($prop_info, $value),
        default => $value,
      };
    }

    // Slots.
    $slots = [];
    foreach ($this->options['slots'] ?? [] as $slot_name => $slot_info) {
      /** @var string */
      $field_id = $slot_info['field'];
      if ($field_id) {
        $slots[$slot_name] = ['#markup' => $this->view->style_plugin->getField($row_index, $field_id)];
      }
    }

    // Return the mapped component with view fields mapped to props and slots.
    return [
      '#type' => 'component',
      '#component' => $this->options['component_id'],
      '#props' => $props,
      '#slots' => $slots,
    ];
  }

  /**
   * Check if property contains identifiable boolean.
   *
   * @param string $rendered
   *   The rendered property value.
   *
   * @return bool
   *   True if identifiable true value, otherwise false.
   */
  protected function booleanProp(string $rendered):bool {
    // Get rendered value and strip out tags.
    $value = trim(strip_tags($rendered ?? ''));
    if (is_numeric($value)) {
      // If rendered value is numeric, 0 is FALSE, anything else is TRUE.
      if ((int) $value == 0) {
        return FALSE;
      }
      return TRUE;
    }
    // Check for common text boolean true values.
    if (strcasecmp($value, 'true') == 0) {
      return TRUE;
    }
    if (strcasecmp($value, 'yes') == 0) {
      return TRUE;
    }
    if (strcasecmp($value, 'on') == 0) {
      return TRUE;
    }
    // Nothing that could be identified as true found, so evaluate false.
    return FALSE;
  }

  /**
   * Check if property contains identifiable boolean.
   *
   * @param string $rendered
   *   The rendered property value.
   *
   * @return int
   *   If a number was found, return it. If not return zero.
   */
  protected function integerProp(string $rendered):int {
    // Get rendered value and strip out tags.
    $value = trim(strip_tags($rendered ?? ''));
    if (is_numeric($value)) {
      // If rendered value is numeric, 0 is FALSE, anything else is TRUE.
      return (int) $value;
    }
    // No number in string, so return zero.
    return 0;
  }

  /**
   * For string props, check type of field expected.
   *
   * @param array $prop_info
   *   An array describing the property.
   * @param \Drupal\Component\Render\MarkupInterface|string $rendered
   *   The rendered property value.
   * @param string $raw
   *   The unrendered value of the property.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The appropriate string value for the prop type.
   */
  protected function stringProp(array $prop_info, MarkupInterface|string $rendered, string|NULL $raw):MarkupInterface|string {
    // If prop type is enum, return the raw string value.
    if (array_key_exists('enum', $prop_info) && is_array($prop_info['enum'])) {
      // If no raw value provided, such as a custom text field, use stripped
      // version of rendered value.
      if (is_null($raw)) {
        return trim(strip_tags($rendered));
      }
      // Return raw value for enum props.
      return $raw;
    }
    // No special treatment required, so return the rendered value.
    return $rendered;
  }

  /**
   * For string props, check type of field expected.
   *
   * @param array $prop_info
   *   An array describing the property.
   * @param \Drupal\Component\Render\MarkupInterface|string $rendered
   *   The rendered property value.
   *
   * @return array
   *   The a render array.
   */
  protected function objectProp(array $prop_info, MarkupInterface|string $rendered):array {
    // If rendered field is empty, return an empty array.
    if (trim($rendered) == '') {
      return [];
    }
    // Currently we just wrap the rendered element in a render array, but in
    // future we might require additional processing.
    return ['#markup' => $rendered];
  }

}
