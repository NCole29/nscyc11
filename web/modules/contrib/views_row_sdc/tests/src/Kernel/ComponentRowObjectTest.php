<?php

declare(strict_types=1);

namespace Drupal\Tests\views_row_sdc\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\views\Views;
use Drupal\views\Entity\View;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test description.
 */
#[Group('views_row_sdc')]
#[RunTestsInSeparateProcesses]
final class ComponentRowObjectTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'views',
    'views_row_sdc',
    'views_row_sdc_test',
  ];

  /**
   * Stores a file for user image.
   *
   * @var \Drupal\file\Entity\File
   */
  protected File $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installConfig(['field']);
    $this->installEntitySchema('view');
    $this->installSchema('file', ['file_usage']);

    // Create field storage (defines the field globally).
    FieldStorageConfig::create([
      'field_name' => 'field_picture',
      'entity_type' => 'user',
      'type' => 'image',
    ])->save();

    // Attach field to the user bundle.
    FieldConfig::create([
      'field_name' => 'field_picture',
      'entity_type' => 'user',
      'bundle' => 'user',
      'label' => 'Picture',
    ])->save();

    $directory = 'public://test-images';
    \Drupal::service('file_system')->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY
    );

    // Tiny 1x1 PNG.
    $image_data = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9WQAAAAASUVORK5CYII='
    );
    $uri = $directory . '/test.png';
    file_put_contents($uri, $image_data);

    $this->file = File::create([
      'uri' => $uri,
    ]);
    $this->file->save();
  }

  /**
   * Test mapping of True property to SDC enum property.
   */
  public function testObjectValidPropMapping(): void {
    // Create a user.
    $account = $this->createUser([], NULL, FALSE, [
      'name' => 'Test User',
      'field_picture' => [
        [
          'target_id' => $this->file->id(),
          'alt' => 'Test picture',
          'title' => 'Test picture',
        ],
      ],
    ]);

    $output = $this->buildAndRenderView();

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
    $this->assertStringContainsString('/files/test-images/test.png', $output);
    $this->assertStringContainsString('alt="Test picture"', $output);
    $this->assertStringContainsString('title="Test picture"', $output);
  }

  /**
   * Test mapping of False property to SDC enum property.
   */
  public function testObjectInvalidPropMapping(): void {
    // Create a user.
    $account = $this->createUser([], NULL, FALSE, ['name' => 'Test User']);

    $output = $this->buildAndRenderView();

    // Assert title is rendered.
    $this->assertStringContainsString('Test User', $output);
    $this->assertStringNotContainsString('Image:', $output);
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
              'picture' => [
                'id' => 'picture',
                'table' => 'user__field_picture',
                'field' => 'field_picture',
              ],
            ],
            'row' => [
              'type' => 'sdc_component',
              'options' => [
                'component_id' => 'views_row_sdc_test:object_card',
                'props' => [
                  'title' => [
                    'field' => 'name',
                    'type' => 'string',
                  ],
                  'image' => [
                    'field' => 'picture',
                    'type' => 'object',
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
