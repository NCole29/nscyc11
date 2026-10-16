<?php

declare(strict_types=1);

namespace Drupal\Tests\views_row_sdc\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\views\Views;
use Drupal\views\Entity\View;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test description.
 */
#[Group('views_row_sdc')]
#[RunTestsInSeparateProcesses]
final class ComponentRowBooleanTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'views',
    'views_row_sdc',
    'views_row_sdc_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installConfig(['field']);
    $this->installEntitySchema('view');

    // Create field storage (defines the field globally).
    FieldStorageConfig::create([
      'field_name' => 'field_flag',
      'entity_type' => 'user',
      'type' => 'string',
    ])->save();

    // Attach field to the user bundle.
    FieldConfig::create([
      'field_name' => 'field_flag',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Flag',
    ])->save();

    // Create a View.
    $view = View::create([
      'id' => 'test_view',
      'base_table' => 'users_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'fields' => [
              'name' => [
                'id' => 'name',
                'table' => 'users_field_data',
                'field' => 'name',
              ],
              'flag' => [
                'id' => 'flag',
                'table' => 'user__field_flag',
                'field' => 'field_flag',
              ],
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:boolean_card',
                'props' => [
                  'title' => [
                    'field' => 'name',
                    'static' => '',
                    'type' => 'string',
                  ],
                  'flag' => [
                    'field' => 'flag',
                    'static' => '',
                    'type' => 'boolean',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();
  }

  /**
   * Build and render the view containing boolean property.
   *
   * @return string
   *   The rendered view.
   */
  protected function buildAndRenderView(): string {

    // Execute view.
    $view = Views::getView('test_view');
    $view->execute();

    $build = $view->buildRenderable();

    // Render to HTML.
    $renderer = $this->container->get('renderer');
    return (string) $renderer->renderRoot($build);
  }

  /**
   * Test mapping of True property to SDC boolean property.
   */
  public function testBooleanTruePropMapping(): void {
    // Create a user.
    $this->createUser([], NULL, FALSE, ['name' => 'Test User', 'field_flag' => 'True']);

    $output = $this->buildAndRenderView();

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
    $this->assertStringContainsString('Flag is on', $output);
  }

  /**
   * Test mapping of False property to SDC boolean property.
   */
  public function testBooleanFalsePropMapping(): void {
    // Create a user.
    $this->createUser([], NULL, FALSE, ['name' => 'Test User', 'field_flag' => '0']);

    $output = $this->buildAndRenderView();

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
    $this->assertStringContainsString('Flag is off', $output);
  }

  /**
   * Test building form for SDC with boolean prop.
   */
  public function testBooleanPropBuildForm(): void {
    $view = Views::getView('test_view');
    $view->setDisplay('default');
    $view->initDisplay();
    $row_plugin = $view->display_handler->getPlugin('row');

    // Build form first.
    $form = [];
    $form_state = new FormState();

    // Build form.
    $row_plugin->buildOptionsForm($form, $form_state);
    // Assert form values.
    $this->assertArrayHasKey('component_id', $form);
    $this->assertEquals('views_row_sdc_test:boolean_card', $form['component_id']['#default_value']);
    $this->assertEquals('field:name', $form['sdc_wrapper']['props']['title']['#default_value']);
    $this->assertEquals('field:flag', $form['sdc_wrapper']['props']['flag']['#default_value']);
  }

  /**
   * Test submitting form for SDC with boolean prop.
   */
  public function testBooleanPropSubmitForm(): void {
    $view = Views::getView('test_view');
    $view->setDisplay('default');
    $view->initDisplay();
    $row_plugin = $view->display_handler->getPlugin('row');

    // Build form first.
    $form = [];
    $form_state = new FormState();

    // Build form.
    $row_plugin->buildOptionsForm($form, $form_state);

    // Set submitted values in form - swap name and flag around.
    $form_state->setValues([
      'row_options' => [
        'component_id' => 'views_row_sdc_test:boolean_card',
        'sdc_wrapper' => [
          'props' => [
            'title' => 'field:flag',
            'flag' => 'field:name',
          ],
        ],
      ],
    ]);

    // Submit the form.
    $row_plugin->submitOptionsForm($form, $form_state);
    $row_plugin->unpackOptions($row_plugin->options, $form_state->getValues()['row_options']);

    // Assert plugin options saved.
    $this->assertEquals('views_row_sdc_test:boolean_card', $row_plugin->options['component_id']);
    $this->assertEquals(['field' => 'flag', 'static' => '', 'type' => 'string'], $row_plugin->options['props']['title']);
    $this->assertEquals(['field' => 'name', 'static' => '', 'type' => 'boolean'], $row_plugin->options['props']['flag']);
  }

  /**
   * Test submitting form for SDC with boolean prop with static value.
   */
  public function testBooleanPropStaticSubmitForm(): void {
    $view = Views::getView('test_view');
    $view->setDisplay('default');
    $view->initDisplay();
    $row_plugin = $view->display_handler->getPlugin('row');

    // Build form first.
    $form = [];
    $form_state = new FormState();

    // Build form.
    $row_plugin->buildOptionsForm($form, $form_state);

    // Set submitted values in form - swap name and flag around.
    $form_state->setValues([
      'row_options' => [
        'component_id' => 'views_row_sdc_test:boolean_card',
        'sdc_wrapper' => [
          'props' => [
            'title' => 'field:name',
            'flag' => 'static:1',
          ],
        ],
      ],
    ]);

    // Submit the form.
    $row_plugin->submitOptionsForm($form, $form_state);
    $row_plugin->unpackOptions($row_plugin->options, $form_state->getValues()['row_options']);

    // Assert plugin options saved.
    $this->assertEquals('views_row_sdc_test:boolean_card', $row_plugin->options['component_id']);
    $this->assertEquals(['field' => 'name', 'static' => '', 'type' => 'string'], $row_plugin->options['props']['title']);
    $this->assertEquals(['field' => '', 'static' => '1', 'type' => 'boolean'], $row_plugin->options['props']['flag']);
  }

  /**
   * Test submitting form for SDC with boolean prop with no field selected.
   */
  public function testBooleanPropEmptySubmitForm(): void {
    $view = Views::getView('test_view');
    $view->setDisplay('default');
    $view->initDisplay();
    $row_plugin = $view->display_handler->getPlugin('row');

    // Build form first.
    $form = [];
    $form_state = new FormState();

    // Build form.
    $row_plugin->buildOptionsForm($form, $form_state);

    // Set submitted values in form - set flag to "- none -".
    $form_state->setValues([
      'row_options' => [
        'component_id' => 'views_row_sdc_test:boolean_card',
        'sdc_wrapper' => [
          'props' => [
            'title' => 'field:name',
            'flag' => '',
          ],
        ],
      ],
    ]);

    // Submit the form.
    $row_plugin->submitOptionsForm($form, $form_state);
    $row_plugin->unpackOptions($row_plugin->options, $form_state->getValues()['row_options']);

    // Assert plugin options saved.
    $this->assertEquals('views_row_sdc_test:boolean_card', $row_plugin->options['component_id']);
    $this->assertEquals(['field' => 'name', 'static' => '', 'type' => 'string'], $row_plugin->options['props']['title']);
    // None selected for "flag". Both field and static should be blank.
    $this->assertEquals(['field' => '', 'static' => '', 'type' => 'boolean'], $row_plugin->options['props']['flag']);
  }

}
