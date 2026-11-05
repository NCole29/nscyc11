<?php

namespace Drupal\Tests\mailer_policy\Kernel;

use Drupal\Tests\symfony_mailer\DummyHttpsWrapper;
use Drupal\Tests\symfony_mailer\Kernel\SymfonyMailerKernelTestBase;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Tests EmailAdjuster plug-ins.
 *
 * @group symfony_mailer
 */
class EmailAdjusterTest extends SymfonyMailerKernelTestBase {

  protected const IMAGE_URL = "/image.png";

  /**
   * Inline CSS adjuster test.
   */
  public function testInlineCss() {
    // Test an email including the test library.
    $this->testMailer->addCallback(function (EmailInterface $email) {
      $email->addLibrary('symfony_mailer_test/inline_css_test');
    });
    $this->testMailer->verify($this->addressTo);

    $this->readMail();
    // The inline CSS from inline.text-small.css should appear.
    $this->assertBodyContains('<h4 class="text-small" style="padding-top: 3px; padding-bottom: 3px; text-align: center; color: white; background-color: #0678be; font-size: smaller; font-weight: bold;">');
    // The imported CSS from inline.day.css should appear.
    $this->assertBodyContains('<span class="day" style="font-style: italic;">');
  }

  /**
   * Sender adjuster test.
   */
  public function testSenderAdjuster() {
    $this->testMailer->verify($this->addressTo);
    $this->readMail();

    // Check the default sender is used with default configuration.
    $this->assertSender('sender@example.com', 'Example');

    // Configure the adjuster.
    $policyConfig = $this->config('mailer_policy.mailer_policy._');
    $config = $policyConfig->get('configuration');
    $config['email_sender'] = [
      'addresses' => [
        [
          'value' => 'testsender@example.com',
          'display' => 'testdisplay',
        ],
      ],
    ];
    $policyConfig->set('configuration', $config)->save();

    // Send another email.
    $this->testMailer->verify($this->addressTo);
    $this->readMail();

    // Verify the message gets the custom sender.
    $this->assertSender('testsender@example.com', 'testdisplay');
  }

  /**
   * Tests the InlineImagesEmailAdjuster adjuster.
   */
  public function testInlineImagesEmailAdjuster() {
    DummyHttpsWrapper::register();

    // Configure a body with an image.
    $config = $this->config('mailer_policy.mailer_policy.symfony_mailer.verify');
    $config->set('configuration.email_body.content.value', '<img src="' . self::IMAGE_URL . '"/>');
    $config->save();

    // Check the image is not inline.
    $this->testMailer->verify($this->addressTo);
    $this->readMail();
    // VerifyMailer sets 1 attachment.
    $this->assertCount(1, $this->email->getAttachments());
    $this->assertBodyContains(self::IMAGE_URL);
    $this->assertBodyNotContains('img src="cid:');

    // Configure inline images.
    $config->set('configuration.mailer_inline_images', []);
    $config->save();

    // Check the image is inline.
    $this->testMailer->verify($this->addressTo);
    $this->readMail();
    $this->assertCount(2, $this->email->getAttachments());
    $this->assertAttachment($this->absoluteUri(self::IMAGE_URL), embed: TRUE);
    $this->assertBodyNotContains(self::IMAGE_URL);
    $this->assertBodyContains('img src="cid:');
  }

}
