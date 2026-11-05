<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\symfony_mailer\LegacyConverterInterface;

/**
 * Provides a Mailer Plus replacement for MailManager.
 */
class MailManagerReplacement extends MailManager implements MailManagerReplacementInterface {

  /**
   * Constructs the MailManagerReplacement object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\mailer_override\OverrideManagerInterface $overrideManager
   *   The override manager.
   * @param \Drupal\symfony_mailer\LegacyConverterInterface $legacyConverter
   *   The legacy converter.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    TranslationInterface $string_translation,
    RendererInterface $renderer,
    protected readonly OverrideManagerInterface $overrideManager,
    protected readonly LegacyConverterInterface $legacyConverter,
  ) {
    parent::__construct($namespaces, $cache_backend, $module_handler, $config_factory, $logger_factory, $string_translation, $renderer);
  }

  /**
   * {@inheritdoc}
   */
  public function mail($module, $key, $to, $langcode, $params = [], $reply = NULL, $send = TRUE): array {
    if (isset($params['__email'])) {
      // This email is intended for the legacy mail system, so send via the
      // parent class.
      return parent::mail($module, $key, $to, $langcode, $params, $reply, $send);
    }

    $message = [
      'id' => $module . '_' . $key,
      'module' => $module,
      'key' => $key,
      'to' => $to ?: NULL,
      'langcode' => $langcode,
      'params' => $params,
      'reply-to' => $reply,
      'send' => $send,
    ];

    // Send an email from the array.
    $override = $this->overrideManager->createInstanceFromMessage($message);
    $override->send($message, new LegacyProcessor($this->legacyConverter, $message));
    // If the result is empty then we must have skipped sending. Set 'result'
    // to NULL, because FALSE indicates an error in sending.
    $message['result'] ??= NULL;

    return $message;
  }

}
