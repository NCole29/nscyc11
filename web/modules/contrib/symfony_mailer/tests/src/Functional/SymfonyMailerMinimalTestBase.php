<?php

namespace Drupal\Tests\symfony_mailer\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Base class for Mailer Plus browser tests without mailer_transport.
 */
abstract class SymfonyMailerMinimalTestBase extends BrowserTestBase {

  /**
   * Human-readable string representing 'all'.
   */
  protected const TYPE_ALL = '<b>*All*</b>';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['symfony_mailer', 'mailer_policy'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to manage mailer settings.
   */
  protected UserInterface $adminUser;

  /**
   * The site name.
   */
  protected string $siteName = 'Tom & Jerry';

  /**
   * The site email.
   */
  protected string $siteEmail = 'site@example.org';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')
      ->set('name', $this->siteName)
      ->set('mail', $this->siteEmail)
      ->save();
    $this->adminUser = $this->drupalCreateUser(['administer mailer']);
  }

}
