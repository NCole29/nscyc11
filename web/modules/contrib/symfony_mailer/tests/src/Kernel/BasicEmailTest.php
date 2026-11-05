<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

/**
 * Tests basic module functions.
 *
 * @group symfony_mailer
 */
class BasicEmailTest extends SymfonyMailerKernelTestBase {

  /**
   * Basic email sending test.
   */
  public function testEmail() {
    // Test email error.
    $this->testMailer->verify('zzz');
    $this->noMail();

    // Test email success.
    $this->testMailer->verify($this->addressTo);
    $this->readMail();
    $this->assertSubject("Verification email from Example");
    $this->assertTo($this->addressTo);
    $this->assertEquals('https://www.drupal.org/project/symfony_mailer', $this->findLink('Mailer Plus'));
  }

}
