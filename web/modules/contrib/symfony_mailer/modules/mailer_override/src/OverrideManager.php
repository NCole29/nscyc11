<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\mailer_override\Plugin\Mailer\LegacyMailer;
use Drupal\mailer_policy\PolicyHelperInterface;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\MailerLookupInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mailer override plugin manager.
 */
class OverrideManager extends DefaultPluginManager implements OverrideManagerInterface {

  use StringTranslationTrait;

  /**
   * The cache key for saving the labels.
   */
  protected const string LABELS_CACHE_KEY = 'mailer_override_labels';

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The override config storage.
   */
  protected ExtensionInstallStorage $overrideStorage;

  /**
   * Mapping from override state code to human-readable state string.
   *
   * @var string[]
   */
  protected array $stateName;

  /**
   * Array of action names.
   *
   * This a 2-dimensional array indexed by override state code and action code.
   *
   * @var string[][]
   */
  protected array $actionName;

  /**
   * Mapping from action code to human-readable warning string.
   *
   * @var string[]
   */
  protected array $actionWarning;

  /**
   * The config prefix for the MailerPolicy entity type.
   */
  protected string $policyConfigPrefix;

  /**
   * Array of registered override plugin settings.
   *
   * The key is the email ID to override and the value is the plugin ID.
   *
   * @var string[]
   */
  protected ?array $overrideMapping = NULL;

  /**
   * Array of form alter configuration.
   *
   * The key is the form ID and the value is an array of alterations.
   */
  protected ?array $formAlter = NULL;

  /**
   * Array of plugin labels, saved from the Mailer configuration.
   */
  protected ?array $labels = NULL;

  /**
   * Constructs the OverrideManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\symfony_mailer\MailerLookupInterface $mailerLookup
   *   The mailer lookup service.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The configuration manager.
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The config storage.
   * @param \Drupal\mailer_override\ImportHelperInterface $importHelper
   *   The import helper.
   * @param \Drupal\mailer_policy\PolicyHelperInterface $policyHelper
   *   The policy helper.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected readonly MailerLookupInterface $mailerLookup,
    protected readonly ConfigManagerInterface $configManager,
    #[Autowire(service: 'config.storage')]
    protected readonly StorageInterface $configStorage,
    protected readonly ImportHelperInterface $importHelper,
    protected readonly PolicyHelperInterface $policyHelper,
    protected readonly Token $token,
  ) {
    parent::__construct('Plugin/MailerOverride', $namespaces, $module_handler, 'Drupal\mailer_override\OverrideInterface', 'Drupal\mailer_override\Attribute\Override');
    $this->setCacheBackend($cache_backend, 'mailer_override_definitions');
    $this->alterInfo('mailer_override_info');
    $this->entityTypeManager = $configManager->getEntityTypeManager();
    $this->configFactory = $configManager->getConfigFactory();
    $this->overrideStorage = new ExtensionInstallStorage($this->configStorage, 'config/mailer_override', StorageInterface::DEFAULT_COLLECTION, FALSE, '');

    $this->stateName = [
      self::STATE_DISABLED => $this->t('Disabled'),
      self::STATE_ENABLED => $this->t('Enabled'),
      self::STATE_IMPORTED => $this->t('Enabled & imported'),
    ];
    $this->actionName = [
      self::STATE_DISABLED => [
        'import' => $this->t('Enable & import'),
        'enable' => $this->t('Enable'),
        'disable' => $this->t('Delete'),
      ],
      self::STATE_ENABLED => [
        'import' => $this->t('Import'),
        'disable' => $this->t('Disable'),
        'enable' => $this->t('Reset'),
      ],
      self::STATE_IMPORTED => [
        'disable' => $this->t('Disable'),
        'enable' => $this->t('Reset'),
        'import' => $this->t('Re-import'),
      ],
      self::ALL_OVERRIDES => [
        'import' => $this->t('Enable & import'),
        'enable' => $this->t('Enable'),
        'disable' => $this->t('Disable'),
      ],
    ];
    $this->actionWarning = [
      'disable' => $this->t('Related Mailer Policy will be deleted.'),
      'enable' => $this->t('Related Mailer Policy will be reset to default values.'),
      'import' => $this->t('Importing overwrites existing policy.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(string $id): bool {
    $settings_key = str_replace('.', '__', $id);
    $state = $this->configFactory->get('mailer_override.settings')->get("override.$settings_key") ?: self::STATE_DISABLED;
    return $state != self::STATE_DISABLED;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(?string $filterId = NULL): array {
    if ($filterId == self::ALL_OVERRIDES) {
      return [
        'name' => $this->t('<b>*All*</b>'),
        'warning' => '',
        'state_name' => '',
        'import' => '',
        'import_warning' => '',
        'action_names' => $this->actionName[self::ALL_OVERRIDES],
      ];
    }

    $this->fetchLabels();
    $settings = $this->configFactory->get('mailer_override.settings')->get('override');
    $info = [];
    $matched = [];

    // Get all definitions, including disabled.
    foreach ($this->findDefinitions() as $id => $definition) {
      $settings_key = str_replace('.', '__', $id);
      $matched[$settings_key] = TRUE;
      if (!isset($settings[$settings_key])) {
        $settings[$settings_key] = self::STATE_DISABLED;
        $save = TRUE;
      }
      $state = $settings[$settings_key];
      $action_names = $this->actionName[$state];
      if (!$definition['import']) {
        unset($action_names['import']);
      }
      if ($definition['import_warning']) {
        // Move import to the end.
        $import = $action_names['import'];
        unset($action_names['import']);
        $action_names['import'] = $import;
      }

      $info[$id] = [
        'name' => $this->labels[$id] ?? $definition['id'],
        'warning' => $definition['warning'],
        'state' => $state,
        'state_name' => $this->stateName[$state],
        'import' => $definition['import'],
        'import_warning' => $definition['import_warning'],
        'action_names' => $action_names,
      ];
    }

    if (!empty($save) || (count($settings) > count($matched))) {
      // Fix missing or extra values in settings.
      $settings = array_intersect_key($settings, $matched);
      $this->configFactory->getEditable('mailer_override.settings')->set('override', $settings)->save();
    }

    ksort($info);
    return $filterId ? ($info[$filterId] ?? NULL) : $info;
  }

  /**
   * {@inheritdoc}
   */
  public function action(string $id, string $action, bool $confirming = FALSE): ?array {
    $info = $this->getInfo($id);
    if (empty($info['action_names'][$action])) {
      throw new \LogicException("Invalid override action '$action'");
    }

    if ($id == self::ALL_OVERRIDES) {
      [$steps, $warnings] = $this->bulkActionSteps($action);
    }
    else {
      $steps[$id] = $action;
    }

    if ($confirming) {
      // Return warnings.
      if (!$steps) {
        return NULL;
      }
      if ($info['warning'] && ($info['state'] == self::STATE_DISABLED) && ($action != 'disable')) {
        $warnings[] = $info['warning'];
      }
      if ($action == 'import' && $info['import_warning']) {
        $warnings[] = $info['import_warning'];
      }
      $warnings[] = $this->actionWarning[$action];
      return $warnings;
    }

    foreach ($steps as $loop_id => $loop_action) {
      $this->doAction($loop_id, $loop_action);
    }

    // Clear cached Mailer definitions.
    $this->mailerLookup->clearCachedDefinitions();
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    // Normally, the provider is defaulted from the namespace, but we prefer
    // instead to set from the ID. This allows one module to proxy a
    // definition for another, and it will be ignored if the target module
    // isn't enabled.
    $definition['provider'] = explode('.', $plugin_id)[0];

    // Default overrides.
    if (!$definition['override']) {
      $definition['override'] = [$plugin_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    parent::clearCachedDefinitions();
    $this->overrideMapping = NULL;
    $this->formAlter = NULL;
    $this->labels = NULL;
    $this->cacheBackend->delete(self::LABELS_CACHE_KEY);
  }

  /**
   * Creates a plugin instance from a legacy message array.
   *
   * @param array $message
   *   The message.
   *
   * @return \Drupal\mailer_override\OverrideInterface
   *   The override instance.
   *
   * @internal
   */
  public function createInstanceFromMessage(array $message): OverrideInterface {
    if (is_null($this->overrideMapping)) {
      $this->overrideMapping = [];
      foreach ($this->getDefinitions() as $id => $definition) {
        foreach ($definition['override'] as $override_id) {
          $this->overrideMapping[$override_id] = $id;
        }
      }
    }

    $suggestions = [
      "$message[module].$message[key]",
      $message['module'],
    ];

    foreach ($suggestions as $plugin_id) {
      if ($override_id = $this->overrideMapping[$plugin_id] ?? NULL) {
        return $this->createInstance($override_id);
      }
    }

    $mailer = LegacyMailer::create(\Drupal::getContainer(), $message['module']);
    return new LegacyOverride($mailer);
  }

  /**
   * Alters Mailer definitions based on override configuration.
   *
   * @param array $definitions
   *   The discovered plugin definitions.
   *
   * @internal
   */
  public function alterMailerDefinitions(&$definitions): void {
    // Save labels from Mailers to use for our definitions.
    $this->labels = [];
    foreach ($definitions as $id => $def) {
      $this->labels[$id] = $def['label'];
    }
    $this->cacheSet(self::LABELS_CACHE_KEY, $this->labels, Cache::PERMANENT);

    // Remove a mailer if there is an override but it's not enabled. This
    // allows LegacyMailer to take over, see below.
    // findDefinitions() includes all definitions and getDefinitions() includes
    // only the enabled ones.
    $remove = array_diff_key($this->findDefinitions(), $this->getDefinitions());
    $definitions = array_diff_key($definitions, $remove);

    // Add definitions for any implementations of hook_mail() that don't
    // already have one, using LegacyMailer.
    $mail_hooks = [];
    $this->moduleHandler->invokeAllWith('mail', function (callable $hook, string $module) use (&$mail_hooks) {
      $mail_hooks[] = $module;
    });
    $missing = array_diff($mail_hooks, array_keys($definitions));

    foreach ($missing as $type) {
      $definitions[$type] = $this->getLegacyMailerDefinition($type);
    }
  }

  /**
   * Implementation for hook_form_alter().
   *
   * @internal
   */
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (is_null($this->formAlter)) {
      $this->formAlter = [];
      foreach ($this->getDefinitions() as $plugin_id => $definition) {
        foreach ($definition['form_alter'] as $match => $alter) {
          $alter += ['remove' => [], 'default' => [], 'tag' => $plugin_id];

          if ($match == '*') {
            $entity_type = $this->mailerLookup->getTagDefinition($alter['tag'])['metadata_key'];
            $ids = ["{$entity_type}_edit_form", "{$entity_type}_add_form"];
          }
          else {
            $ids = [$match];
          }
          foreach ($ids as $id) {
            // Merge existing values.
            $this->formAlter[$id] = NestedArray::mergeDeep($alter, $this->formAlter[$id] ?? []);
          }
        }
      }
    }

    if ($alter = $this->formAlter[$form_id] ?? NULL) {
      // Hide fields that are replaced by Mailer Policy.
      foreach ($alter['remove'] as $key) {
        $form[$key]['#access'] = FALSE;
      }

      // Set defaults for hidden fields.
      foreach ($alter['default'] as $key => $default) {
        if (empty($form[$key]['#default_value'])) {
          $form[$key]['#default_value'] = $this->token->replace($default);
        }
      }

      // Add policy elements.
      if ($tag = $alter['tag']) {
        $form_object = $form_state->getFormObject();
        $entity = ($form_object instanceof EntityFormInterface) ? $form_object->getEntity() : NULL;
        $form['mailer_policy'] = $this->policyHelper->renderPolicy($tag, $entity);
      }
    }
  }

  /**
   * Get a Mailer plugin definition for a LegacyMailer.
   *
   * @param string $id
   *   The Mailer plugin ID.
   *
   * @return array
   *   The Mailer plugin definition.
   */
  protected function getLegacyMailerDefinition(string $id) {
    $mailer = new MailerInfo($id, sub_defs: ['*' => $this->t('Unknown')]);
    $mailer->setClass(LegacyMailer::class);
    $mailer->setProvider($id);
    $definition = $mailer->get();
    $this->mailerLookup->processDefinition($definition, $id);
    return $definition;
  }

  /**
   * Internal helper function to execute an action.
   *
   * @param string $id
   *   The override ID.
   * @param string $action
   *   The action to execute.
   */
  protected function doAction(string $id, string $action): void {
    // Save the state and clear cached definitions so that we can create a
    // newly enabled instance later in this function.
    $settings = $this->configFactory->getEditable('mailer_override.settings');
    $settings_key = str_replace('.', '__', $id);
    $existing_state = $settings->get("override.$settings_key");
    $new_state = self::ACTIONS[$action];
    $settings->set("override.$settings_key", $new_state)->save();
    $this->clearCachedDefinitions();

    // Find the config names to set or delete. Include "PREFIX" (exact match)
    // or "PREFIX.*" but not "PREFIX_XXX".
    $prefix = $this->getPolicyConfigPrefix() . ".$id";
    $config_names = $this->overrideStorage->listAll("$prefix.");
    if ($this->overrideStorage->exists($prefix)) {
      $config_names[] = $prefix;
    }
    // Find the definition even if it is now disabled.
    $definition = $this->findDefinitions()[$id];
    $config_names = array_merge($config_names, $definition['config']);

    if ($action == 'disable') {
      $this->deleteConfig($config_names);
    }
    else {
      // When importing from disabled state, first have to enable.
      $do_defaults = ($action == 'enable') || ($action == 'import' && $existing_state == self::STATE_DISABLED);

      if ($do_defaults) {
        $this->defaultConfig($config_names);
      }

      if ($action == 'import') {
        $this->createInstance($id)->import($this->importHelper);
      }
    }
  }

  /**
   * Gets the config prefix for the mailer_policy entity type.
   *
   * @return string
   *   The config prefix.
   */
  protected function getPolicyConfigPrefix(): string {
    if (!isset($this->policyConfigPrefix)) {
      // Don't calculate this in the constructor as the entity types may not
      // have loaded yet.
      $this->policyConfigPrefix = $this->entityTypeManager->getDefinition('mailer_policy')->getConfigPrefix();
    }
    return $this->policyConfigPrefix;
  }

  /**
   * Gets the steps required for a bulk override action.
   *
   * @param string $action
   *   The action to execute.
   *
   * @return array
   *   List of two items:
   *   - steps: array keyed by plugin ID with value equal to the action to run.
   *   - warnings: array of warning messages to display.
   */
  protected function bulkActionSteps(string $action): array {
    $steps = [];
    $warnings = [];
    $all_info = $this->getInfo();
    $new_state = self::ACTIONS[$action];

    foreach ($all_info as $id => $info) {
      // Skip if already in the required state.
      if ($info['state'] == $new_state) {
        continue;
      }
      if (($new_state == self::STATE_ENABLED) && ($info['state'] == self::STATE_IMPORTED)) {
        continue;
      }

      // Skip enable if there is a warning.
      $args = array_filter(['%name' => $info['name'], '%warning' => $info['warning'], '%import_warning' => $info['import_warning']]);
      if ($info['warning'] && ($action != 'disable')) {
        $warnings[] = $this->t('Skipped %name: %warning', $args);
        continue;
      }

      // Skip importing if not available or there is a warning.
      if ($action == 'import' && (!$info['import'] || $info['import_warning'])) {
        $loop_action = 'enable';

        if ($info['state'] == self::STATE_ENABLED) {
          continue;
        }

        $warnings[] = $info['import_warning'] ?
          $this->t('Import skipped for %name: %import_warning', $args) :
          $this->t('Import unavailable for %name', $args);
      }
      else {
        $loop_action = $action;
      }

      $warnings[] = $this->t('Run %action for override %name', ['%name' => $info['name'], '%action' => $loop_action]);
      $steps[$id] = $loop_action;
    }

    return [$steps, $warnings];
  }

  /**
   * Sets default configuration for Mailer override.
   *
   * @param string[] $config_names
   *   The configuration names.
   */
  protected function defaultConfig(array $config_names): void {
    foreach ($this->overrideStorage->readMultiple($config_names) as $name => $values) {
      $config_type = $this->configManager->getEntityTypeIdByName($name);
      $storage = $this->entityTypeManager->getStorage($config_type);
      $entity_type = $this->entityTypeManager->getDefinition($config_type);
      $id = ConfigEntityStorage::getIDFromConfigName($name, $entity_type->getConfigPrefix());

      if ($entity = $storage->load($id)) {
        $uuid = $entity->uuid();
        $storage->updateFromStorageRecord($entity, $values);
        $entity->set('uuid', $uuid);
      }
      else {
        $entity = $storage->createFromStorageRecord($values);
      }
      $entity->save();
    }
  }

  /**
   * Deletes configuration.
   *
   * @param string[] $config_names
   *   The configuration names.
   */
  protected function deleteConfig(array $config_names): void {
    // Delete config.
    foreach ($config_names as $name) {
      $this->configStorage->delete($name);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setCachedDefinitions($definitions): void {
    // Filter out disabled definitions. They are still available within this
    // class by calling findDefinitions().
    $definitions = array_filter($definitions, function ($d) {
      return $this->isEnabled($d['id']);
    });
    parent::setCachedDefinitions($definitions);
  }

  /**
   * Fetches labels from cache or Mailer plugins.
   */
  protected function fetchLabels(): void {
    if (is_null($this->labels)) {
      if ($cache = $this->cacheGet(self::LABELS_CACHE_KEY)) {
        // Load from cache.
        $this->labels = $cache->data;
      }
      else {
        // Fetch labels from Mailers.
        $this->mailerLookup->clearCachedDefinitions();
        $this->mailerLookup->getDefinitions();
      }
    }
  }

}
