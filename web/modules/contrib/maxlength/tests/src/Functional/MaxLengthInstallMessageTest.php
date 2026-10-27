<?php

namespace Drupal\Tests\maxlength\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the module install message in the administration UI.
 *
 * @group maxlength
 */
class MaxLengthInstallMessageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['text'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the help message is shown when installing through the UI.
   *
   * @see https://www.drupal.org/project/maxlength/issues/3570975
   */
  public function testInstallMessageShownInUi(): void {
    $admin_user = $this->drupalCreateUser(['administer modules']);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/modules');
    $page = $this->getSession()->getPage();
    $page->checkField('modules[maxlength][enable]');
    $page->pressButton('Install');

    $this->assertSession()->pageTextContains('To set a character limit on a field, visit the Manage form display administration page for the entity the field is associated with.');
    $this->assertSession()->linkExists('MaxLength Help Page');
  }

}
