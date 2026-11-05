<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\symfony_mailer\Component\VerifyMailerInterface;
use Drupal\symfony_mailer_test\MailerTestTrait;

/**
 * Tests basic email sending.
 *
 * @group symfony_mailer
 */
abstract class SymfonyMailerKernelTestBase extends KernelTestBase {

  use MailerTestTrait;
  use RandomGeneratorTrait;

  /**
   * Email address for the tests.
   */
  protected string $addressTo = 'symfony-mailer-to@example.com';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['symfony_mailer', 'symfony_mailer_test', 'mailer_policy', 'mailer_transport', 'system', 'user', 'filter'];

  /**
   * The test mailer.
   *
   * @var \Drupal\symfony_mailer\Component\TestMailerInterface
   */
  protected $testMailer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['symfony_mailer', 'mailer_policy']);
    $this->installEntitySchema('user');
    $this->testMailer = $this->container->get(VerifyMailerInterface::class);
    $this->config('system.site')
      ->set('name', 'Example')
      ->set('mail', 'sender@example.com')
      ->save();
  }

}
