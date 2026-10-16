<?php

declare(strict_types=1);

namespace Drupal\Tests\views_row_sdc\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Views;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\ResultRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test description.
 */
#[Group('views_row_sdc')]
#[RunTestsInSeparateProcesses]
final class ComponentRowTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
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
    $this->installEntitySchema('view');
  }

  /**
   * Test that the component row plugin is present.
   */
  public function testRowPluginExists(): void {
    $manager = \Drupal::service('plugin.manager.views.row');

    $definitions = $manager->getDefinitions();

    $this->assertArrayHasKey('sdc_component', $definitions);
  }

  /**
   * Test that options form contains components.
   */
  public function testOptionsFormHasComponentSelect(): void {
    // Create an instance of the plugin class.
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    // Create stub classes for view and display.
    $view = $this->createStub(ViewExecutable::class);
    $display = $this->createStub(DisplayPluginBase::class);

    $display->method('getFieldLabels')->willReturn([
      'name' => [
        'id' => 'name',
        'label' => 'Name',
      ],
    ]);

    $view->display_handler = $display;

    $plugin->view = $view;

    $form = [];
    $form_state = new FormState();

    $plugin->buildOptionsForm($form, $form_state);

    $this->assertArrayHasKey('component_id', $form);
    $this->assertEquals('select', $form['component_id']['#type']);

    $this->assertNotEmpty($form['component_id']['#options']);
    $this->assertArrayHasKey('views_row_sdc_test:test_card', $form['component_id']['#options']);
  }

  /**
   * Test that options form excludes hidden components.
   */
  public function testOptionsFormHiddenComponent(): void {
    // Create an instance of the plugin class.
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    // Create stub classes for view and display.
    $view = $this->createStub(ViewExecutable::class);
    $display = $this->createStub(DisplayPluginBase::class);

    $display->method('getFieldLabels')->willReturn([
      'name' => [
        'id' => 'name',
        'label' => 'Name',
      ],
    ]);

    $view->display_handler = $display;

    $plugin->view = $view;

    $form = [];
    $form_state = new FormState();

    $plugin->buildOptionsForm($form, $form_state);

    $this->assertArrayHasKey('component_id', $form);
    $this->assertArrayNotHasKey('views_row_sdc_test:hidden_card', $form['component_id']['#options']);
  }

  /**
   * Test that options form excludes obsolete components.
   */
  public function testOptionsFormObsoleteComponent(): void {
    // Create an instance of the plugin class.
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    // Create stub classes for view and display.
    $view = $this->createStub(ViewExecutable::class);
    $display = $this->createStub(DisplayPluginBase::class);

    $display->method('getFieldLabels')->willReturn([
      'name' => [
        'id' => 'name',
        'label' => 'Name',
      ],
    ]);

    $view->display_handler = $display;

    $plugin->view = $view;

    $form = [];
    $form_state = new FormState();

    $plugin->buildOptionsForm($form, $form_state);

    $this->assertArrayHasKey('component_id', $form);
    $this->assertArrayHasKey('views_row_sdc_test:test_card', $form['component_id']['#options']);
    $this->assertArrayNotHasKey('views_row_sdc_test:obsolete_card', $form['component_id']['#options']);
  }

  /**
   * Test that options form boolean field contains "true" and "false" options.
   */
  public function testOptionsFormHasBooleanComponentValues(): void {
    // Create an instance of the plugin class.
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    // Create stub classes for view and display.
    $view = $this->createStub(ViewExecutable::class);
    $display = $this->createStub(DisplayPluginBase::class);

    $display->method('getFieldLabels')->willReturn([
      'name' => [
        'id' => 'name',
        'label' => 'Name',
      ],
    ]);

    $view->display_handler = $display;

    $plugin->view = $view;

    $form = [];
    $form_state = new FormState();
    // Simulate component already selected.
    $form_state->setUserInput(['row_options' => ['component_id' => 'views_row_sdc_test:boolean_card']]);

    $plugin->buildOptionsForm($form, $form_state);

    $this->assertArrayHasKey('component_id', $form);
    $this->assertArrayHasKey('sdc_wrapper', $form);
    $this->assertArrayHasKey('props', $form['sdc_wrapper']);
    $this->assertArrayHasKey('flag', $form['sdc_wrapper']['props']);
    $this->assertArrayHasKey('#options', $form['sdc_wrapper']['props']['flag']);
    $this->assertArrayHasKey('field:name', $form['sdc_wrapper']['props']['flag']['#options']);
    $this->assertArrayHasKey('static:1', $form['sdc_wrapper']['props']['flag']['#options']);
    $this->assertArrayHasKey('static:0', $form['sdc_wrapper']['props']['flag']['#options']);
  }

  /**
   * Test that options form boolean field contains "true" and "false" options.
   */
  public function testOptionsFormHasEnumComponentValues(): void {
    // Create an instance of the plugin class.
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    // Create stub classes for view and display.
    $view = $this->createStub(ViewExecutable::class);
    $display = $this->createStub(DisplayPluginBase::class);

    $display->method('getFieldLabels')->willReturn([
      'name' => [
        'id' => 'name',
        'label' => 'Name',
      ],
    ]);

    $view->display_handler = $display;

    $plugin->view = $view;

    $form = [];
    $form_state = new FormState();
    // Simulate component already selected.
    $form_state->setUserInput(['row_options' => ['component_id' => 'views_row_sdc_test:enum_card']]);

    $plugin->buildOptionsForm($form, $form_state);

    $this->assertArrayHasKey('component_id', $form);
    $this->assertArrayHasKey('sdc_wrapper', $form);
    $this->assertArrayHasKey('props', $form['sdc_wrapper']);
    $this->assertArrayHasKey('alignment', $form['sdc_wrapper']['props']);
    $this->assertArrayHasKey('#options', $form['sdc_wrapper']['props']['alignment']);
    $this->assertArrayHasKey('field:name', $form['sdc_wrapper']['props']['alignment']['#options']);
    // Check alignment keys and values.
    $this->assertArrayHasKey('static:lawful', $form['sdc_wrapper']['props']['alignment']['#options']);
    $this->assertEquals('Totally Lawful', $form['sdc_wrapper']['props']['alignment']['#options']['static:lawful']);
    $this->assertArrayHasKey('static:neutral', $form['sdc_wrapper']['props']['alignment']['#options']);
    $this->assertEquals('Absolutely Neutral', $form['sdc_wrapper']['props']['alignment']['#options']['static:neutral']);
    $this->assertArrayHasKey('static:chaotic', $form['sdc_wrapper']['props']['alignment']['#options']);
    // Note chaotic has no descriptive text.
    $this->assertEquals('chaotic', $form['sdc_wrapper']['props']['alignment']['#options']['static:chaotic']);
  }

  /**
   * Test that options form boolean field contains "true" and "false" options.
   */
  public function testOptionsFormOptionsSetDefaults(): void {
    // Create an instance of the plugin class.
    /** @var \Drupal\views_row_sdc\Plugin\views\row\ComponentRow */
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    // Create stub classes for view and display.
    $view = $this->createStub(ViewExecutable::class);
    $display = $this->createStub(DisplayPluginBase::class);

    $display->method('getFieldLabels')->willReturn([
      'name' => [
        'id' => 'name',
        'label' => 'Name',
      ],
      'bio' => [
        'id' => 'bio',
        'label' => 'Biography',
      ],
      'image' => [
        'id' => 'image',
        'label' => 'Image',
      ],
    ]);

    $view->display_handler = $display;

    $plugin->view = $view;

    $form = [];
    $form_state = new FormState();
    // Simulate component selected.
    $plugin->options = [
      'component_id' => 'views_row_sdc_test:test_card',
      'props' => [
        'title' => ['field' => 'name', 'type' => 'string'],
        'subtitle' => ['field' => 'bio', 'type' => 'string'],
      ],
      'slots' => [
        'media' => ['field' => 'image'],
      ],
    ];

    $plugin->buildOptionsForm($form, $form_state);

    $this->assertArrayHasKey('component_id', $form);
    $this->assertEquals('views_row_sdc_test:test_card', $form['component_id']['#default_value']);

    $this->assertArrayHasKey('sdc_wrapper', $form);
    $this->assertArrayHasKey('props', $form['sdc_wrapper']);
    $this->assertArrayHasKey('title', $form['sdc_wrapper']['props']);
    $this->assertEquals('field:name', $form['sdc_wrapper']['props']['title']['#default_value']);
    $this->assertEquals('field:bio', $form['sdc_wrapper']['props']['subtitle']['#default_value']);
    $this->assertEquals('field:image', $form['sdc_wrapper']['slots']['media']['#default_value']);
  }

  /**
   * Test that the render returns an empty render array if no component picked.
   */
  public function testRenderNoComponentReturnsEmpty(): void {
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    $plugin->options['component_id'] = '';

    $row = new ResultRow();

    $build = $plugin->render($row);

    $this->assertArrayNotHasKey('#type', $build);
    $this->assertArrayNotHasKey('#component', $build);
  }

  /**
   * Test that the render returns a component.
   */
  public function testRenderReturnsComponent(): void {
    $plugin = $this->container
      ->get('plugin.manager.views.row')
      ->createInstance('sdc_component', []);

    $plugin->options['component_id'] = 'views_row_sdc_test:test_card';

    $row = new ResultRow();

    $build = $plugin->render($row);

    $this->assertEquals('component', $build['#type']);
    $this->assertEquals('views_row_sdc_test:test_card', $build['#component']);
  }

  /**
   * Test mapping of title to SDC property.
   */
  public function testNoFieldPropMapping(): void {

    // Create a user.
    $account = $this->createUser([], NULL, FALSE, ['name' => 'Test User']);

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
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:test_card',
                'props' => [
                  'title' => [
                    'field' => '',
                    'static' => '',
                    'type' => 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $view->save();

    // Execute view.
    $view = Views::getView('test_view');
    $view->execute();

    $build = $view->buildRenderable();

    // Render to HTML.
    $renderer = $this->container->get('renderer');
    $output = (string) $renderer->renderRoot($build);

    // Assert title is rendered.
    $this->assertStringContainsString('Title:', $output);
    $this->assertStringNotContainsString('Test User', $output);
  }

  /**
   * Test mapping of title to SDC property.
   */
  public function testTitlePropMapping(): void {
    // Create a user.
    $account = $this->createUser([], NULL, FALSE, ['name' => 'Test User']);

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
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:test_card',
                'props' => [
                  'title' => [
                    'field' => 'name',
                    'type' => 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $view->save();

    // Execute view.
    $view = Views::getView('test_view');
    $view->execute();

    $build = $view->buildRenderable();

    // Render to HTML.
    $renderer = $this->container->get('renderer');
    $output = (string) $renderer->renderRoot($build);

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
  }

  /**
   * Tests that unmapped props are omitted instead of passed as blank strings.
   *
   * A blank string is not a valid value for class-typed props (such as
   * Drupal\Core\Template\Attribute) and fails SDC schema validation.
   */
  public function testUnmappedClassPropSkipped(): void {
    $this->createUser([], NULL, FALSE, ['name' => 'Test User']);

    $view = View::create([
      'id' => 'test_view_class_prop',
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
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:class_card',
                'props' => [
                  'attributes' => [
                    'field' => '',
                    'static' => '',
                    'type' => 'Drupal\Core\Template\Attribute',
                  ],
                  'title' => [
                    'field' => 'name',
                    'type' => 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $view->save();

    $view = Views::getView('test_view_class_prop');
    $view->execute();

    $build = $view->buildRenderable();

    // Rendering must not throw InvalidComponentException for the unmapped
    // class-typed attributes prop.
    $renderer = $this->container->get('renderer');
    $output = (string) $renderer->renderRoot($build);

    $this->assertStringContainsString('Test User', $output);
    $this->assertStringContainsString('class-card', $output);
  }

  /**
   * Test mapping multiple SDC properties.
   */
  public function testMultiplePropMapping(): void {
    $account = $this->createUser([], NULL, FALSE, [
      'uid' => 1001,
      'name' => 'Test User',
    ]);

    $view = View::create([
      'id' => 'test_view_multi',
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
              'uid' => [
                'id' => 'uid',
                'table' => 'users_field_data',
                'field' => 'uid',
              ],
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:test_card',
                'props' => [
                  'title' => [
                    'field' => 'name',
                    'type' => 'string',
                  ],
                  'subtitle' => [
                    'field' => 'uid',
                    'type' => 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();

    $view = Views::getView('test_view_multi');
    $view->execute();

    $build = $view->buildRenderable();

    $output = (string) $this->container->get('renderer')->renderRoot($build);

    $this->assertStringContainsString('Title: <span>Test User</span>', $output);
    $this->assertStringContainsString('Subtitle: 1001', $output);
  }

  /**
   * Test mapping of SDC Slot.
   */
  public function testSlotMapping(): void {
    $account = $this->createUser([], NULL, FALSE, [
      'uid' => 1001,
      'name' => 'Test User',
    ]);

    $view = View::create([
      'id' => 'test_view_slots',
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
              'uid' => [
                'id' => 'uid',
                'table' => 'users_field_data',
                'field' => 'uid',
              ],
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:test_card',
                'props' => [
                  'title' => [
                    'field' => 'name',
                    'type' => 'string',
                  ],
                ],
                'slots' => [
                  'media' => [
                    'field' => 'uid',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $view->save();

    $view = Views::getView('test_view_slots');
    $view->execute();

    $build = $view->buildRenderable();

    $output = (string) $this->container->get('renderer')->renderRoot($build);

    // Both prop and slot should render.
    $this->assertStringContainsString('Title: <span>Test User</span>', $output);
    $this->assertStringContainsString('1001', $output);
  }

}
