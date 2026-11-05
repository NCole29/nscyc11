<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Entity;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\AdjusterPluginCollection;
use Drupal\mailer_policy\EmailAdjusterInterface;
use Drupal\mailer_policy\EmailAdjusterManagerInterface;
use Drupal\symfony_mailer\Attribute\MailerInfo;
use Drupal\symfony_mailer\MailerLookupInterface;

/**
 * Defines a Mailer Policy configuration entity class.
 */
#[ConfigEntityType(
  id: "mailer_policy",
  label: new TranslatableMarkup("Mailer Policy"),
  handlers: [
    "list_builder" => "Drupal\mailer_policy\MailerPolicyListBuilder",
    "form" => [
      "edit" => "Drupal\mailer_policy\Form\PolicyEditForm",
      "add" => "Drupal\mailer_policy\Form\PolicyAddForm",
      "delete" => "Drupal\Core\Entity\EntityDeleteForm",
    ],
  ],
  admin_permission: "administer mailer",
  entity_keys: [
    "id" => "id",
  ],
  links: [
    "edit-form" => "/admin/config/system/mailer/policy/{mailer_policy}",
    "delete-form" => "/admin/config/system/mailer/policy/{mailer_policy}/delete",
    "collection" => "/admin/config/system/mailer/policy",
  ],
  config_export: [
    "id",
    "configuration",
  ]
)]
class MailerPolicy extends ConfigEntityBase implements EntityWithPluginCollectionInterface, MailerPolicyInterface {

  use StringTranslationTrait;

  /**
   * The unique ID of the policy record.
   */
  protected string $id;

  /**
   * The mailer manager.
   */
  protected MailerLookupInterface $mailerLookup;

  /**
   * The email adjuster manager.
   */
  protected EmailAdjusterManagerInterface $emailAdjusterManager;

  /**
   * The label for an unknown value.
   */
  protected MarkupInterface $labelUnknown;

  /**
   * The label for all values.
   */
  protected MarkupInterface $labelAll;

  /**
   * The tag.
   */
  protected string $tag = '';

  /**
   * The tag label.
   *
   * @var \Drupal\Core\StringTranslation\MarkupInterface[]
   */
  protected array $tagLabel = [];

  /**
   * The entity id.
   */
  protected string $entityId = '';

  /**
   * The entity.
   */
  protected ?ConfigEntityInterface $entity = NULL;

  /**
   * The mailer definition.
   */
  protected array $mailerDefinition = [];

  /**
   * Configuration for this policy record.
   *
   * An associative array of email adjuster configuration, keyed by the plug-in
   * ID with value as an array of configured settings.
   */
  protected array $configuration = [];

  /**
   * The collection of email adjuster plug-ins configured in this policy.
   */
  protected AdjusterPluginCollection $pluginCollection;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->emailAdjusterManager = \Drupal::service(EmailAdjusterManagerInterface::class);
  }

  /**
   * Parses the Mailer definition.
   *
   * This isn't done in the constructor for performance reasons. We don't need
   * it when sending an email.
   */
  protected function parse(): void {
    if (isset($this->mailerLookup)) {
      return;
    }
    $this->mailerLookup = \Drupal::service(MailerLookupInterface::class);
    $this->labelUnknown = $this->t('Unknown');
    $this->labelAll = $this->t('<b>*All*</b>');

    // The root policy with ID '_' applies to all tags.
    if (!$this->id || ($this->id == '_')) {
      $this->fillDummy($this->labelAll);
      return;
    }

    [$this->tag, $this->entityId] = array_pad(explode('..', $this->id), 2, '');
    $this->mailerDefinition = $this->mailerLookup->getTagDefinition($this->tag);

    if (!$this->mailerDefinition) {
      $this->fillDummy($this->labelUnknown);
      return;
    }

    $this->tagLabel = $this->mailerDefinition['labels'];
    if ($this->mailerDefinition['sub_defs']) {
      $this->tagLabel[] = $this->labelAll;
    }

    // Load the entity.
    if ($this->entityId && $meta_key = $this->mailerDefinition['metadata_key']) {
      $this->entity = $this->entityTypeManager()->getStorage($meta_key)->load($this->entityId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTag(): string {
    $this->parse();
    return $this->tag;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ?ConfigEntityInterface {
    $this->parse();
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTagLabel(int $skip = 0): MarkupInterface {
    $this->parse();
    return Markup::create(implode(' » ', array_slice($this->tagLabel, $skip)));
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityLabel(): string|MarkupInterface {
    if (empty($this->getMailerDefinition()['metadata_key'])) {
      return '';
    }
    if ($this->entity) {
      return $this->entity->label() ?? '';
    }
    return $this->entityId ? $this->labelUnknown : $this->labelAll;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string|MarkupInterface {
    $this->parse();
    $labels = $this->tagLabel;
    array_push($labels, $this->getEntityLabel());
    return implode(' » ', array_filter($labels));
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): static {
    $this->configuration = $configuration;
    if (isset($this->pluginCollection)) {
      $this->pluginCollection->setConfiguration($configuration);
    }
    return $this;
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
  public function getMailerDefinition():array {
    $this->parse();
    return $this->mailerDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function adjusters(): AdjusterPluginCollection {
    if (!isset($this->pluginCollection)) {
      $this->pluginCollection = new AdjusterPluginCollection($this->emailAdjusterManager, $this->configuration);
    }
    return $this->pluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function adjusterDefinitions(): array {
    return $this->emailAdjusterManager->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary($expanded = FALSE): string {
    $summary = [];
    $separator = ', ';

    foreach ($this->adjusters()->sort() as $adjuster) {
      $element = $adjuster->getLabel();
      if ($expanded && ($element_summary = $adjuster->getSummary())) {
        if (strlen($element_summary) > EmailAdjusterInterface::MAX_SUMMARY) {
          $element_summary = substr($element_summary, 0, EmailAdjusterInterface::MAX_SUMMARY) . '…';
        }
        $element .= ": $element_summary";
        $separator = '<br>';
      }
      $summary[] = $element;

    }

    return implode($separator, $summary);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['adjusters' => $this->adjusters()];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    if ($this->entity) {
      $this->addDependency('config', $this->entity->getConfigDependencyName());
    }
    elseif ($provider = $this->getMailerDefinition()['provider'] ?? NULL) {
      $this->addDependency('module', $provider);
    }
    return $this;
  }

  /**
   * Fills in values for a 'dummy' policy without a mailer definition.
   *
   * @param \Drupal\Core\StringTranslation\MarkupInterface $label
   *   The label.
   */
  protected function fillDummy(MarkupInterface $label): void {
    $this->tagLabel[] = $label;
    $mailer_info = new MailerInfo($this->id);
    $mailer_info->setClass('');
    $this->mailerDefinition = $mailer_info->get();
    $this->mailerLookup->processDefinition($this->mailerDefinition, $this->id);
    unset($this->mailerDefinition['provider']);
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b): int {
    return strnatcasecmp($a->label(), $b->label());
  }

  /**
   * {@inheritdoc}
   */
  public static function sortSpecific(ConfigEntityInterface $a, ConfigEntityInterface $b): int {
    return count(explode('.', $b->id())) <=> count(explode('.', $a->id())) ?:
      static::sort($a, $b);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadOrCreate(string $id): static {
    return static::load($id) ?? static::create(['id' => $id]);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadInheritedConfig(string $id, bool $includeSelf = TRUE): array {
    $mailer_lookup = \Drupal::service(MailerLookupInterface::class);
    $ids = $config = [];
    [$loop_id, $entity_id] = array_pad(explode('..', $id), 2, '');

    for ($tag = $id; $tag; $tag = $mailer_lookup->parentTag($tag)) {
      if ($entity_id) {
        $ids[] = "$tag..$entity_id";
      }
      $ids[] = $tag;
    }

    foreach ($ids as $loop_id) {
      if ($includeSelf || $loop_id != $id) {
        if ($policy = static::load($loop_id)) {
          $config += $policy->getConfiguration();
        }
      }
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public static function import(string $id, array $configuration): void {
    $policy = static::loadOrCreate($id);
    $configuration += $policy->getConfiguration();

    $inherited = static::loadInheritedConfig($id, FALSE);
    foreach (array_keys($configuration) as $key) {
      if (isset($inherited[$key]) && static::identicalArray($configuration[$key], $inherited[$key])) {
        unset($configuration[$key]);
      }
    }

    if ($configuration) {
      $policy->setConfiguration($configuration)->save();
    }
    else {
      $policy->delete();
    }
  }

  /**
   * Compares two arrays recursively.
   *
   * @param array $a
   *   The first array.
   * @param array $b
   *   The second array.
   *
   * @return bool
   *   TRUE if the arrays are identical.
   */
  protected static function identicalArray(array $a, array $b): bool {
    if (count($a) != count($b)) {
      return FALSE;
    }

    foreach ($a as $key => $value_a) {
      if (!isset($b[$key])) {
        return FALSE;
      }
      $value_b = $b[$key];
      if (is_array($value_a) && is_array($value_b)) {
        if (!static::identicalArray($value_a, $value_b)) {
          return FALSE;
        }
      }
      elseif ($value_a != $value_b) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
