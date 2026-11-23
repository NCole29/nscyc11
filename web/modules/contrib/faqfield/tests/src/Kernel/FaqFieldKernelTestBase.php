<?php

declare(strict_types=1);

namespace Drupal\Tests\faqfield\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;

/**
 * Sets up an entity_test bundle with a FAQ field for kernel tests.
 */
abstract class FaqFieldKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'faqfield',
    'field',
    'filter',
    'jquery_ui_accordion',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['filter']);
    FilterFormat::create(['format' => 'raw', 'name' => 'Raw'])->save();
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_faq',
      'entity_type' => 'entity_test',
      'type' => 'faqfield',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Creates and saves a test entity.
   *
   * @param array<int, array<string, mixed>> $items
   *   The FAQ items.
   */
  protected function createEntity(array $items): FieldableEntityInterface {
    $entity = EntityTest::create(['field_faq' => $items]);
    $entity->save();
    return $entity;
  }

  /**
   * Renders the FAQ field with the given formatter.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to render.
   * @param string $type
   *   The formatter plugin ID.
   * @param array<string, mixed> $settings
   *   Formatter settings.
   */
  protected function renderField(FieldableEntityInterface $entity, string $type, array $settings = []): string {
    $build = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'status' => TRUE,
    ])->setComponent('field_faq', ['type' => $type, 'settings' => $settings])
      ->build($entity);
    $html = (string) \Drupal::service('renderer')->renderInIsolation($build['field_faq']);
    return preg_replace('/>\s+</', '><', $html);
  }

}
