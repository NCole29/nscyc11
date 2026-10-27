<?php

namespace Drupal\Tests\maxlength\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the module install hook in a command line context.
 *
 * @group maxlength
 */
class MaxLengthInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'field', 'filter', 'text'];

  /**
   * Tests that no message is set when installing from the command line.
   *
   * Generating the help page link during "drush site:install" can fail with
   * a ReflectionException because not all module classes are available yet,
   * so the message is skipped entirely in CLI context. Kernel tests run
   * under the "cli" PHP SAPI, which is exactly the context to cover.
   *
   * @see https://www.drupal.org/project/maxlength/issues/3570975
   */
  public function testNoInstallMessageInCli(): void {
    $this->container->get('module_installer')->install(['maxlength']);

    $messages = \Drupal::messenger()->all();
    $this->assertSame([], $messages, 'No message is set when installing in CLI context.');
  }

}
