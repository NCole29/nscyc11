<?php

namespace Drupal\Tests\symfony_mailer\Functional;

use Drupal\Core\Test\AssertMailTrait;

/**
 * Test forwarding to the legacy mail manager.
 *
 * @group symfony_mailer
 */
class LegacyMailManagerTest extends SymfonyMailerMinimalTestBase {

  use AssertMailTrait;

  /**
   * Test delivering via the two channels.
   */
  public function testMailManager() {
    \Drupal::service('module_installer')->uninstall(['mailer_transport']);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/mailer');
    $this->submitForm([], 'Send');
    $this->assertMailString('subject', "Verification email from $this->siteName", 1);

    // @todo Verify more fields, using HTML-mail: requires symfony_mailer_lite.
  }

}
