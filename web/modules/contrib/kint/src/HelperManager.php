<?php

namespace Drupal\kint;

use Kint\Renderer\ConstructableRendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Kint\Kint;
use Kint\Utils;

/**
 * Creates and executes kint helper functions.
 */
class HelperManager {

  const HELPER_CONFIG_PREFIX = 'kint.helper.';

  use StringTranslationTrait;

  /**
   * List of loaded helper configurations.
   *
   * @var \Drupal\Core\Config\ImmutableConfig[]
   */
  protected array $helpers = [];

  /**
   * Static list of loaded helper configurations.
   *
   * @var array<string, true>
   */
  private static array $historicalHelpers = [];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected MessengerInterface $messenger,
  ) {
    $helper_configs = $configFactory->listAll(self::HELPER_CONFIG_PREFIX);

    $failed = [];

    foreach ($helper_configs as $id) {
      assert(is_string($id));
      $count = 1;
      $name = str_replace(self::HELPER_CONFIG_PREFIX, '', $id, $count);
      try {
        $this->registerHelper($name);
      }
      catch (\DomainException $e) {
        $failed[$name] = $e;
      }
    }

    if ($failed) {
      $this->messenger->addWarning($this->t(
            "The following helpers could not be defined: @helpers",
            ['@helpers' => implode(', ', array_keys($failed))]
        ));
    }
  }

  /**
   * Creates a global function that proxies into $this->executeHelper().
   */
  public function registerHelper(string $name): void {
    if (!Utils::isValidPhpName($name)) {
      throw new \InvalidArgumentException('Helper name ' . $name . ' is invalid');
    }

    // If any instance of this class has registered the stub,
    // we don't have to do it again, nor register it with Kint.
    if (isset(self::$historicalHelpers[$name])) {
      return;
    }

    if (function_exists($name)) {
      throw new \DomainException('Helper name ' . $name . ' is already defined');
    }

    // phpcs:disable
    eval('function ' . $name . ' (...$args){
            return \Drupal::service("kint.helper.manager")->executeHelper(' . var_export($name, TRUE) . ', $args);
        }');
    // phpcs:enable
    self::$historicalHelpers[$name] = TRUE;
    Kint::$aliases[] = $name;
  }

  /**
   * Executes a kint dump helper based on configuration.
   *
   * @param string $name
   *   The name of the helper.
   * @param mixed[] $args
   *   The arguments passed to the dumper.
   */
  public function executeHelper(string $name, array $args): mixed {
    $helper = $this->getHelper($name);
    $mode = $helper->get('mode');
    $renderer = $helper->get('renderer');
    assert(is_string($renderer) && is_a($renderer, ConstructableRendererInterface::class, TRUE));

    $stash = [];
    $stash['enabled_mode'] = Kint::$enabled_mode;
    $stash['mode_default'] = Kint::$mode_default;
    $stash['cli_detection'] = Kint::$cli_detection;
    $stash['return'] = Kint::$return;
    $stash['renderer'] = Kint::$renderers[Kint::MODE_RICH];

    try {
      Kint::$enabled_mode = Kint::$enabled_mode !== FALSE;
      Kint::$mode_default = Kint::MODE_RICH;
      assert(is_bool($helper->get('cli_detection')));
      Kint::$cli_detection = $helper->get('cli_detection');
      Kint::$renderers[Kint::MODE_RICH] = $renderer;

      if ($mode === 'messenger') {
        Kint::$return = TRUE;
      }

      $output = Kint::dump(...$args);
    }
    finally {
      Kint::$enabled_mode = $stash['enabled_mode'];
      Kint::$mode_default = $stash['mode_default'];
      Kint::$cli_detection = $stash['cli_detection'];
      Kint::$return = $stash['return'];
      Kint::$renderers[Kint::MODE_RICH] = $stash['renderer'];
    }

    if ($mode === 'messenger') {
      $this->messenger->addMessage(Markup::create($output), MessengerInterface::TYPE_STATUS, TRUE);
      return 0;
    }
    if ($mode === 'exit') {
      exit;
    }

    return $output;
  }

  /**
   * Gets a helper config.
   */
  protected function getHelper(string $name): ImmutableConfig {
    if (!isset($this->helpers[$name])) {
      $this->helpers[$name] = $this->configFactory->get(self::HELPER_CONFIG_PREFIX . $name);
    }

    return $this->helpers[$name];
  }

}
