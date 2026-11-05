<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

use Drupal\TestTools\Random;
use Drupal\Tests\symfony_mailer\DummyHttpsWrapper;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\Attachment;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Tests \Drupal\symfony_mailer\EmailInterface.
 *
 * @group symfony_mailer
 */
class EmailInterfaceTest extends SymfonyMailerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem(): void {
    $private_file_directory = $this->siteDirectory . '/private';
    $this->setSetting('file_private_path', $private_file_directory);
  }

  /**
   * Tests setting and getting attachments.
   */
  public function testAttachments() {
    global $base_url;
    $logo = \Drupal::service('extension.list.module')->getPath('symfony_mailer') . '/logo.png';
    $logo_uri = \Drupal::service('file_url_generator')->generateString($logo);
    DummyHttpsWrapper::register(['settings.php' => FALSE]);

    // Send.
    $this->testMailer->addCallback(function (EmailInterface $email) {
      $email->attach(Attachment::fromData('Blue cheese', 'cheese.txt', 'text/plain'))
        ->attachFromPath('public://image.jpg')
        ->attachFromPath('private://image.jpg')
        ->attachFromPath('/root/image.jpg')
        ->attach(Attachment::fromPath('sites/default/settings.php'))
        ->attach(Attachment::fromPath('http://zzz'))
        ->removeAttachment('http://zzz');
    });
    $this->testMailer->verify($this->addressTo);

    // Check.
    $this->readMail();
    $this->assertAttachment(NULL, 'cheese.txt', 'text/plain');
    $this->assertAttachment('public://image.jpg', NULL, 'image/jpeg');
    $this->assertNoAttachment('private://image.jpg');
    $this->assertNoAttachment('/root/image.jpg');
    $this->assertNoAttachment(name: 'settings.php');
    $this->assertAttachment("$base_url$logo_uri", NULL, 'image/png', TRUE);
    $this->assertCount(3, $this->email->getAttachments());
  }

  /**
   * Tests setting and getting email addresses for the 5 types.
   *
   * @param mixed $addresses
   *   The email addresses.
   *
   * @dataProvider emailAddressesProvider
   */
  public function testEmailAddresses($addresses) {
    $this->testMailer->addCallback(function (EmailInterface $email) use ($addresses) {
      // Sets a random header value to ensure its overrides works correctly.
      foreach (['from', 'reply-to', 'cc', 'bcc'] as $name) {
        $email->setAddress($name, $this->randomMachineName() . '@example.com');
      }

      // Set and get each address field.
      $count = is_array($addresses) ? count($addresses) : (is_null($addresses) ? 0 : 1);
      $email->setFrom($addresses);
      $this->assertCount($count, $email->getFrom());
      $email->setReplyTo($addresses);
      $this->assertCount($count, $email->getReplyTo());
      $this->assertCount(1, $email->getTo());
      $email->setCc($addresses);
      $this->assertCount($count, $email->getCc());
      $email->setBcc($addresses);
      $this->assertCount($count, $email->getBcc());
    });
    $this->testMailer->verify($this->addressTo);

    // Assert a test email with header exists.
    $this->readMail();
    $s_addresses = $addresses ? array_map(fn($a) => $a->getSymfony(), Address::convert($addresses)) : NULL;
    foreach (['from', 'reply-to', 'cc', 'bcc'] as $name) {
      $this->assertAddress($name, $s_addresses);
    }
  }

  /**
   * Data provider for ::testEmailAddresses().
   */
  public static function emailAddressesProvider(): array {
    $addresses = [
      '<site>',
      Random::machineName() . '@example.com',
      new Address(Random::machineName() . '@example.com'),
      new Address(Random::machineName() . '@example.com', Random::machineName()),
    ];

    // Tests header erasing.
    $data[] = [
      'addresses' => NULL,
    ];

    // Tests the header with a single address value.
    foreach ($addresses as $address) {
      $data[] = [
        'addresses' => $address,
      ];
    }

    // Tests with multiple addresses for the header.
    $data[] = [
      'addresses' => $addresses,
    ];

    return $data;
  }

}
