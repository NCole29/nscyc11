<?php

declare(strict_types=1);

namespace Drupal\Tests\views_row_sdc\Kernel;

use Drupal\views\Views;
use Drupal\views\Entity\View;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test description.
 */
#[Group('views_row_sdc')]
#[RunTestsInSeparateProcesses]
final class ComponentRowCustomTextTest extends KernelTestBase {

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
  }

  /**
   * Test mapping of True property to SDC enum property.
   */
  public function testEnumCustomTextPropMapping(): void {
    // Create a user.
    $account = $this->createUser([], NULL, FALSE, ['name' => 'Test User', 'field_alignment' => 'lawful']);

    $output = $this->buildAndRenderView();

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
    $this->assertStringContainsString('chaotic', $output);
  }

  /**
   * Build and render the view containing enum property.
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
              'nothing' => [
                'id' => 'nothing',
                'table' => 'views',
                'field' => 'nothing',
                'alter' => [
                  'alter_text' => TRUE,
                  'text' => 'chaotic',
                ],
              ],
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:enum_card',
                'props' => [
                  'title' => [
                    'field' => 'name',
                    'type' => 'string',
                  ],
                  'alignment' => [
                    'field' => 'nothing',
                    'type' => 'string',
                    'enum' => ['lawful', 'neutral', 'chaotic'],
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
