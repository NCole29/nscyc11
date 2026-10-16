<?php

declare(strict_types=1);

namespace Drupal\Tests\views_row_sdc\Kernel;

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
final class ComponentRowIntegerTest extends KernelTestBase {

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
      'field_name' => 'field_count',
      'entity_type' => 'user',
      'type' => 'string',
    ])->save();

    // Attach field to the user bundle.
    FieldConfig::create([
      'field_name' => 'field_count',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Count',
    ])->save();
  }

  /**
   * Test mapping of True property to SDC boolean property.
   */
  public function testInteger23PropMapping(): void {
    // Create a user.
    $account = $this->createUser([], NULL, FALSE, ['name' => 'Test User', 'field_count' => '23']);

    $output = $this->buildAndRenderView();

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
    $this->assertStringContainsString('23 available', $output);
  }

  /**
   * Test mapping of False property to SDC boolean property.
   */
  public function testIntegerZeroPropMapping(): void {
    // Create a user.
    $account = $this->createUser([], NULL, FALSE, ['name' => 'Test User', 'field_flag' => '0']);

    $output = $this->buildAndRenderView();

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
    $this->assertStringContainsString('0 available', $output);
  }

  /**
   * Build and render the view containing boolean property.
   *
   * @return string
   *   The rendered view.
   */
  protected function buildAndRenderView(): string {
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
              'count' => [
                'id' => 'count',
                'table' => 'user__field_count',
                'field' => 'field_count',
              ],
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:integer_card',
                'props' => [
                  'title' => [
                    'field' => 'name',
                    'type' => 'string',
                  ],
                  'count' => [
                    'field' => 'count',
                    'type' => 'integer',
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
    return (string) $renderer->renderRoot($build);
  }

}
