<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Plugin\TransportUI;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\mailer_transport\TransportUIInterface;

/**
 * Base class for TransportUI plug-ins.
 */
abstract class TransportUIBase extends PluginBase implements TransportUIInterface {

  /**
   * {@inheritdoc}
   */
  public function getDsn(): string {
    $cfg = $this->configuration;
    $default_cfg = $this->defaultConfiguration();

    // Remove default values from query string.
    $query = !empty($cfg['query']) ? array_diff_assoc($cfg['query'], $default_cfg['query']) : [];

    $dsn = $this->getPluginId() . '://' .
      (!empty($cfg['user']) ? urlencode($cfg['user']) : '') .
      (!empty($cfg['pass']) ? ':' . urlencode($cfg['pass']) : '') .
      (!empty($cfg['user']) ? '@' : '') .
      (urlencode($cfg['host'] ?? 'default')) .
      (isset($cfg['port']) ? ':' . $cfg['port'] : '') .
      ($query ? '?' . http_build_query($query) : '');

    return $dsn;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

}
