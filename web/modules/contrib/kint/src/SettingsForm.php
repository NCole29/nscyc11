<?php

declare(strict_types=1);

namespace Drupal\kint;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\NestedArray;
use Kint\Kint;
use Kint\Utils;
use Kint\Renderer\CliRenderer;
use Kint\Renderer\PlainRenderer;
use Kint\Renderer\RichRenderer;
use Kint\Renderer\TextRenderer;

/**
 * Settings for kint module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'kint_admin_settings';
  }

  /**
   * {@inheritDoc}
   *
   * @return string[]
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames(): array {
    return ['kint.settings'];
  }

  /**
   * {@inheritDoc}
   *
   * @param mixed[] $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed[]
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('kint.settings');

    $old_return = Kint::$return;
    Kint::$return = TRUE;
    $demo_dump = Kint::dump($config);
    Kint::$return = $old_return;

    $form['demo'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Demo dump'),
      'dump' => [
        '#markup' => Markup::create($demo_dump),
      ],
      '#description' => $this->t("This will demonstrate the current Kint settings by dumping its config object. If you don't see anything here, check your permissions. If you clicked the folder icon and are wondering where it went check the bottom of your window."),
    ];

    $form['early_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable early dump'),
      '#description' => $this->t('Whether to enable dumping during load before authentication gives us access to permissions.'),
      '#default_value' => $config->get('early_enable'),
    ];

    $theme_options = [
      'original.css' => $this->t('Default'),
      'aante-light.css' => $this->t('Aante light'),
      'aante-dark.css' => $this->t('Aante dark'),
      'solarized.css' => $this->t('Solarized'),
      'solarized-dark.css' => $this->t('Solarized dark'),
      'custom' => $this->t('Custom'),
    ];
    $selected_theme = $config->get('rich_theme');
    if (!is_string($selected_theme)) {
      $selected_theme = '';
    }

    $theme_is_custom = !isset($theme_options[$selected_theme]);

    $form['rich_theme'] = [
      '#tree' => TRUE,
      'select' => [
        '#type' => 'select',
        '#options' => $theme_options,
        '#title' => $this->t('Rich renderer theme'),
        '#description' => $this->t('Kint theme to use for dumps.'),
        '#default_value' => $theme_is_custom ? 'custom' : $selected_theme,
      ],
      'text' => [
        '#type' => 'textfield',
        '#title' => $this->t('Custom theme path'),
        '#description' => $this->t('Full path to the custom CSS file. If the dump looks messed up after this you got the path wrong.'),
        '#default_value' => $theme_is_custom ? $config->get('rich_theme') : '',
        '#states' => [
          'visible' => [
            ':input[name="rich_theme[select]"]' => ['value' => 'custom'],
          ],
        ],
      ],
    ];

    $date_format = $config->get('date_format');
    $form['date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date format'),
      '#description' => $this->t(
        'Format for date in dump footer. See <a target="_blank" href=":url">the PHP documentation</a>.',
        [':url' => 'https://www.php.net/datetime.format#refsect1-datetime.format-parameters'],
      ),
      '#default_value' => is_string($date_format) ? $date_format : '',
    ];

    $devel_enabled = 'kint' === $this->config('devel.settings')->get('devel_dumper');

    $form['devel'] = [
      '#type' => 'details',
      '#open' => $devel_enabled,
      '#title' => $this->t('Devel integration'),
      'contents' => [
        'demo' => NULL,
        'use_kint_trace_in_devel' => [
          '#type' => 'checkbox',
          '#title' => $this->t("Override Devel's trace"),
          '#description' => $this->t("Whether to use Kint's trace instead of Devel's in ddebug_backtrace."),
          '#default_value' => $config->get('use_kint_trace_in_devel'),
        ],
      ],
    ];

    if ($devel_enabled) {
      ob_start();
      // phpcs:disable
      devel_dump($config, '$config');
      ddebug_backtrace();
      // phpcs:enable
      $demo_dump = ob_get_clean();

      $form['devel']['contents']['demo'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Demo devel dumps'),
        'dump' => [
          '#markup' => Markup::create($demo_dump),
        ],
      ];
    }
    else {
      $form['devel']['contents']['demo'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => 'messages messages--warning'],
        'value' => [
          'header' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => 'messages__header'],
            'value' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#attributes' => ['class' => 'messages__title'],
              '#value' => $this->t('Warning'),
            ],
          ],
          'content' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => 'messages__content'],
            '#value' => $this->t('Devel is not installed and/or Kint is not the selected Devel dumper.'),
          ],
        ],
      ];
    }

    $form['helpers_wrapper'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Helper functions'),
      'helpers' => [
        '#type' => 'table',
        '#id' => 'kint-helpers-table',
        '#tree' => TRUE,
        '#header' => [
          'name' => $this->t('Name'),
          'renderer' => $this->t('Renderer'),
          'cli_detection' => $this->t('CLI detection'),
          'mode' => $this->t('Dump mode'),
          'delete' => $this->t('Remove'),
        ],
      ],
    ];

    $helper_configs = $this->configFactory()->listAll(HelperManager::HELPER_CONFIG_PREFIX);

    if (!$form_state->isSubmitted()) {
      $form_state->set('helpers_count', count($helper_configs));
      foreach ($helper_configs as $i => $config) {
        assert(is_string($config));
        $name = str_replace(HelperManager::HELPER_CONFIG_PREFIX, '', $config);
        $config = $this->config($config);

        $form['helpers_wrapper']['helpers'][$i] = $this->makeHelperRow($i);
        assert(is_array($form['helpers_wrapper']['helpers'][$i]['name']));
        $form['helpers_wrapper']['helpers'][$i]['name']['#default_value'] = $name;
        assert(is_array($form['helpers_wrapper']['helpers'][$i]['renderer']));
        $form['helpers_wrapper']['helpers'][$i]['renderer']['#default_value'] = $config->get('renderer');
        assert(is_array($form['helpers_wrapper']['helpers'][$i]['cli_detection']));
        $form['helpers_wrapper']['helpers'][$i]['cli_detection']['#default_value'] = $config->get('cli_detection');
        assert(is_array($form['helpers_wrapper']['helpers'][$i]['mode']));
        $form['helpers_wrapper']['helpers'][$i]['mode']['#default_value'] = $config->get('mode');
      }
    }
    else {
      $helpers_count = $form_state->get('helpers_count');
      for ($i = 0; $i < $helpers_count; $i++) {
        // This assert has to be inside the loop for the same reason the asserts
        // above have to be between each line: drupal does some black magic with
        // phpstan that makes it assume any array could magically turn into a
        // MarkupInterface at any time, probably to make using render arrays
        // "easier". Well that doesn't work when you max out phpstan so here
        // we are.
        assert(is_array($form['helpers_wrapper']['helpers']));
        $form['helpers_wrapper']['helpers'][$i] = $this->makeHelperRow($i);
      }
    }

    $form['helpers_wrapper']['add_helper'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#name' => 'add_helper',
      '#submit' => [[$this, 'addHelperSubmit']],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [$this, 'helperAjax'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds a fresh row of the helper table.
   *
   * @return mixed[]
   *   A render array for the row of helper inputs.
   */
  private function makeHelperRow(int $i): array {
    return [
      'name' => [
        '#type' => 'textfield',
        '#element_validate' => [[$this, 'isValidFunctionName']],
      ],
      'renderer' => [
        '#type' => 'select',
        '#options' => [
          RichRenderer::class => $this->t('Rich'),
          PlainRenderer::class => $this->t('Plain'),
          CliRenderer::class => $this->t('Cli'),
          TextRenderer::class => $this->t('Text'),
        ],
        '#default_value' => RichRenderer::class,
      ],
      'cli_detection' => [
        '#type' => 'checkbox',
        '#default_value' => TRUE,
      ],
      'mode' => [
        '#type' => 'select',
        '#options' => [
          'default' => $this->t('Normal dump'),
          'exit' => $this->t('Dump & die'),
          'messenger' => $this->t('Dump to messenger'),
        ],
        '#default_value' => 'default',
      ],
      'delete' => [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_helper__' . $i,
        '#submit' => [[$this, 'deleteHelperSubmit']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'helperAjax'],
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   *
   * @param mixed[] $form
   *   The input form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('kint.settings');
    $config->set('early_enable', $form_state->getValue('early_enable'));

    $theme = $form_state->getValue('rich_theme');
    if (!is_array($theme)) {
      throw new \UnexpectedValueException();
    }

    $theme['select'] ??= 'original.css';
    if ('custom' === $theme['select']) {
      if (!isset($theme['text'])) {
        throw new \UnexpectedValueException();
      }
      $theme = $theme['text'];
    }
    else {
      $theme = $theme['select'];
    }
    $config->set('rich_theme', $theme);

    $date_format = $form_state->getValue('date_format');
    $config->set('date_format', is_string($date_format) && strlen($date_format) ? $date_format : NULL);

    $config->set('use_kint_trace_in_devel', $form_state->getValue('use_kint_trace_in_devel'));

    $config->save();

    $new_helpers = [];
    $inputs = $form_state->getValue('helpers');
    // For some reason no helpers defaults to empty string "" instead of null.
    if (!is_array($inputs)) {
      $inputs = [];
    }
    foreach ($inputs as $input) {
      assert(
        is_array($input)
        && isset($input['name']) && is_string($input['name'])
        && isset($input['renderer'])
        && isset($input['cli_detection'])
        && isset($input['mode'])
      );
      if (!strlen(trim($input['name']))) {
        continue;
      }

      $new_helpers[HelperManager::HELPER_CONFIG_PREFIX . $input['name']] = [
        'renderer' => $input['renderer'],
        'cli_detection' => $input['cli_detection'],
        'mode' => $input['mode'],
      ];
    }

    /** @var string[] $helper_configs */
    $helper_configs = $this->configFactory()->listAll(HelperManager::HELPER_CONFIG_PREFIX);

    /** @var string[] $remove_configs */
    $remove_configs = array_diff($helper_configs, array_keys($new_helpers));
    foreach ($remove_configs as $config) {
      $config = $this->configFactory()->getEditable($config);
      $config->delete();
    }

    foreach ($new_helpers as $name => $values) {
      $config = $this->configFactory()->getEditable($name);
      foreach ($values as $name => $value) {
        $config->set($name, $value);
      }
      $config->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Adds a row to the form.
   *
   * @param mixed[] $form
   *   The input form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addHelperSubmit(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('helpers_count');
    assert(is_numeric($count));
    $form_state->set('helpers_count', $count + 1);
    $form_state->setRebuild();
  }

  /**
   * Removes a row from the form and clears its input.
   *
   * @param mixed[] $form
   *   The input form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function deleteHelperSubmit(array $form, FormStateInterface $form_state): void {
    $count = $form_state->get('helpers_count');
    assert(is_numeric($count));
    $form_state->set('helpers_count', $count - 1);
    $button = $form_state->getTriggeringElement();
    assert(isset($button['#parents']) && is_array($button['#parents']));
    $parents = $button['#parents'];

    $user_input = $form_state->getUserInput();

    // Move from button to row and unset it.
    array_pop($parents);
    NestedArray::unsetValue($user_input, $parents);
    // Move from row to all rows and reset them.
    array_pop($parents);
    $rows = NestedArray::getValue($user_input, $parents);
    assert(is_array($rows));
    NestedArray::setValue($user_input, $parents, array_values($rows));

    $form_state->setUserInput($user_input);
    $form_state->setRebuild();
  }

  /**
   * Sends the table back to the browser.
   *
   * @param mixed[] $form
   *   The input form.
   */
  public function helperAjax(array $form): AjaxResponse {
    assert(is_array($form['helpers_wrapper']) && is_array($form['helpers_wrapper']['helpers']));
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#kint-helpers-table', $form['helpers_wrapper']['helpers']));

    return $response;
  }

  /**
   * Validates that helper names are valid php function names.
   *
   * @param mixed[] $element
   *   The input element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function isValidFunctionName(array &$element, FormStateInterface $form_state): void {
    if ($element['#value'] !== '') {
      if (!is_string($element['#value'])) {
        $form_state->setError($element, $this->t('Invalid function name.'));
      }
      elseif (!Utils::isValidPhpName($element['#value'])) {
        $form_state->setError($element, $this->t('"@value" is not a valid function name.', ['@value' => $element['#value']]));
      }
    }
  }

}
