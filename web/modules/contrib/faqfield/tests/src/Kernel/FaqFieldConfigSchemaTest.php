<?php

declare(strict_types=1);

namespace Drupal\Tests\faqfield\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\SchemaCheckTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the default settings of every plugin match the config schema.
 */
#[Group('faqfield')]
#[RunTestsInSeparateProcesses]
final class FaqFieldConfigSchemaTest extends FaqFieldKernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * Tests the saved view display validates for each formatter.
   */
  #[DataProvider('formatters')]
  public function testFormatterSettingsMatchSchema(string $formatter): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_faq', ['type' => $formatter]);
    $display->save();
    $this->assertConfigSchema(\Drupal::service('config.typed'), $display->getConfigDependencyName(), $this->config($display->getConfigDependencyName())->get());
  }

  /**
   * Tests the saved form display validates with the default widget.
   */
  public function testWidgetSettingsMatchSchema(): void {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_faq', ['type' => 'faqfield_default']);
    $display->save();
    $this->assertConfigSchema(\Drupal::service('config.typed'), $display->getConfigDependencyName(), $this->config($display->getConfigDependencyName())->get());
  }

  /**
   * Provides formatter plugin IDs.
   *
   * @return array<string, array<int, string>>
   *   Formatter IDs keyed by name.
   */
  public static function formatters(): array {
    return FaqFieldOutputTest::formatters();
  }

}
