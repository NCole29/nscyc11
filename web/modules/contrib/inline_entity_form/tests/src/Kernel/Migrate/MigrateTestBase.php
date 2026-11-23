<?php

declare(strict_types=1);

namespace Drupal\Tests\inline_entity_form\Kernel\Migrate;

// Migrate Drupal was removed from core in Drupal 12. Fall back to
// KernelTestBase so the tests can still be discovered, setUp() skips them.
if (class_exists('Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase')) {
  class_alias('Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase', __NAMESPACE__ . '\MigrateDrupalTestBaseCompat');
}
else {
  class_alias('Drupal\KernelTests\KernelTestBase', __NAMESPACE__ . '\MigrateDrupalTestBaseCompat');
}

/**
 * Base class for migration tests.
 */
abstract class MigrateTestBase extends MigrateDrupalTestBaseCompat {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (!class_exists('Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase')) {
      $this->markTestSkipped('Migrate Drupal is not available.');
    }
    parent::setUp();
    $this->loadFixture($this->getFixtureFilePath());
  }

  /**
   * Gets the path to the fixture file.
   */
  protected function getFixtureFilePath() {
    return __DIR__ . '/../../../fixtures/drupal7-small.php';
  }

}
