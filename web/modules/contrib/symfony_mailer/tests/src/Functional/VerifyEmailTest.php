<?php

namespace Drupal\Tests\symfony_mailer\Functional;

use Drupal\Component\Utility\Html;

/**
 * Test the verification email.
 *
 * @group symfony_mailer
 */
class VerifyEmailTest extends SymfonyMailerTestBase {

  /**
   * Test sending a verification email.
   */
  public function testVerify() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/mailer');

    $this->assertPolicyListingIntro('Mailer Plus', 'Subject, Body');
    $this->assertPolicyListingRow(1, 'Verification email', 'Body<br>Subject: Verification email from [site:name]', 'symfony_mailer.verify');

    $this->submitForm([], 'Send');
    $this->assertSession()->pageTextContains('An attempt has been made to send an email to you.');
    $this->readMail();
    $this->assertTo($this->adminUser->getEmail(), $this->adminUser->getDisplayName());
    $this->assertSubject("Verification email from $this->siteName");
    $escaped_site_name = Html::escape($this->siteName);
    $this->assertBodyContains("This is a verification email from <a href=\"$this->baseUrl/\">$escaped_site_name</a>.");

    // Check that inline styles are preserved in the email.
    // The padding is added in email-wrap.html.twig.
    $this->assertBodyContains('style="padding: 0px 0px 0px 0px;"');
    // This style comes from test.email.css.
    $this->assertBodyContains('style="padding-top: 3px; padding-bottom: 3px; text-align: center; color: white; background-color: #0678be;"');
  }

}
