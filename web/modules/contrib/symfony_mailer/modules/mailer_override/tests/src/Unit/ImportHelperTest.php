<?php

namespace Drupal\Tests\mailer_override\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\mailer_override\ImportHelper;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\LegacyConverter;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\mailer_override\ImportHelper
 *
 * @group mailer_override
 */
class ImportHelperTest extends TestCase {

  /**
   * The config factory mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The system.site config mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Config\Config
   */
  protected $siteConfig;

  /**
   * The class under test.
   *
   * @var \Drupal\mailer_override\ImportHelper
   */
  protected $importHelper;

  /**
   * Sets up the test environment.
   *
   * Mocks the configuration factory and site config to be used by the
   * ImportHelper instance.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->siteConfig = $this->createMock(Config::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('system.site')
      ->willReturn($this->siteConfig);

    $this->importHelper = new ImportHelper($this->configFactory, new LegacyConverter());
  }

  /**
   * Tests parsing an address with a display name.
   *
   * Verifies that the `parseAddress` method correctly parses an email
   * address with a display name, ensuring the returned Address object
   * contains the expected email and display name.
   */
  public function testParseAddressWithDisplayName() {
    $addresses = $this->importHelper->parseAddress('Test User <test@example.com>');

    $this->assertCount(1, $addresses);
    $this->assertInstanceOf(Address::class, $addresses[0]);
    $this->assertEquals('test@example.com', $addresses[0]->getEmail());
    $this->assertEquals('Test User', $addresses[0]->getDisplayName());
  }

  /**
   * Tests parsing an address without a display name.
   *
   * Verifies that the `parseAddress` method correctly parses an email
   * address without a display name, ensuring the returned Address object
   * contains the expected email and a null display name.
   */
  public function testParseAddressWithoutDisplayName() {
    $addresses = $this->importHelper->parseAddress('test@example.com');

    $this->assertCount(1, $addresses);
    $this->assertEquals('test@example.com', $addresses[0]->getEmail());
    $this->assertSame('', $addresses[0]->getDisplayName());
  }

  /**
   * Tests the policyFromPlainBody method.
   *
   * Verifies that the `policyFromPlainBody` method correctly returns a policy
   * with the given plain text body.
   */
  public function testPolicyFromPlainBody() {
    $body = 'This is a plain text body.';
    $expected = [
      'content' => [
        'value' => $body,
        'format' => 'plain_text',
      ],
    ];

    $this->assertEquals($expected, $this->importHelper->policyFromPlainBody($body));
  }

  /**
   * Tests the policyFromAddresses method with site mail.
   *
   * Ensures that when the email address matches the site mail, the
   * returned policy contains '<site>' as the value and an empty display name.
   */
  public function testPolicyFromAddressesWithSiteMail() {
    $this->siteConfig->method('get')
      ->with('mail')
      ->willReturn('test@example.com');

    $address = new Address('test@example.com', 'Test User');
    $result = $this->importHelper->policyFromAddresses([$address]);

    $this->assertEquals([
      'addresses' => [
        [
          'value' => '<site>',
          'display' => '',
        ],
      ],
    ], $result);
  }

  /**
   * Tests that the config method returns the expected config factory.
   *
   * Verifies that the `config` method of the ImportHelper class returns
   * the same config factory instance that was injected during its
   * construction.
   */
  public function testConfigReturnsConfigFactory() {
    $this->assertSame($this->configFactory, $this->importHelper->config());
  }

}
