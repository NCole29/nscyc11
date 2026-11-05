<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\symfony_mailer\LegacyConverterInterface;

/**
 * Provides the import helper service.
 */
class ImportHelper implements ImportHelperInterface {

  /**
   * Constructs the ImportHelper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\symfony_mailer\LegacyConverterInterface $legacyConverter
   *   The legacy converter.
   *
   * @internal
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LegacyConverterInterface $legacyConverter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function parseAddress(string $encoded, string $langcode = '', ?AccountInterface $account = NULL): array {
    return $this->legacyConverter->parseAddress($encoded, $langcode, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function policyFromAddresses(array $addresses): array {
    $site_mail = $this->configFactory->get('system.site')->get('mail');

    foreach ($addresses as $address) {
      $value = $address->getEmail();
      $display = '';
      if ($value == $site_mail) {
        $value = '<site>';
      }
      elseif ($user = $address->getAccount()) {
        $value = $user->id();
      }
      else {
        $display = $address->getDisplayName();
      }

      $config['addresses'][] = [
        'value' => $value,
        'display' => $display,
      ];
    }

    return $config ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function policyFromPlainBody(string $body): array {
    return [
      'content' => [
        'value' => $body,
        'format' => 'plain_text',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function config(): ConfigFactoryInterface {
    return $this->configFactory;
  }

}
