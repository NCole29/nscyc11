<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\symfony_mailer\MailerLookupInterface;

/**
 * Provides the policy helper service.
 */
class PolicyHelper implements PolicyHelperInterface {

  use StringTranslationTrait;

  /**
   * Constructs the PolicyHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\mailer_policy\EmailAdjusterManagerInterface $adjusterManager
   *   The email adjuster manager.
   * @param \Drupal\symfony_mailer\MailerLookupInterface $mailerLookup
   *   The mailer lookup service.
   *
   * @internal
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EmailAdjusterManagerInterface $adjusterManager,
    protected readonly MailerLookupInterface $mailerLookup,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function renderPolicy(string $tag, ?ConfigEntityInterface $entity = NULL): array {
    $definition = $this->mailerLookup->getTagDefinition($tag);
    $list_builder = $this->entityTypeManager->getListBuilder('mailer_policy');
    if (!$entity) {
      $list_builder->hideColumns(['entity']);
    }

    // Put an ID for each sub-type.
    $ids = array_map(fn($s) => "$tag.$s", array_keys($definition['sub_defs']));
    if (count($ids) > 1) {
      // If there are multiple IDs than add the "*All*" ID.
      $ids[] = $tag;
    }

    if ($entity && !$entity->isNew()) {
      // Add the ID with the entity suffix.
      $ids = array_merge($ids, array_map(fn($s) => "$s.." . $entity->id(), $ids));
    }

    // Build the policy element.
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mailer policy'),
      '#collapsible' => FALSE,
      '#description' => $this->t('If you have made changes on this page, please save them before editing policy.'),
    ];

    $element['explanation'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Configure Mailer policy records to customise @label emails.', ['@label' => $definition['label']]),
      '#suffix' => '</p>',
    ];

    foreach ($definition['required_config'] as $adjuster_id) {
      $adjuster_names[] = $this->adjusterManager->getDefinition($adjuster_id)['label'];
    }

    if (!empty($adjuster_names)) {
      $element['explanation']['#markup'] .= ' ' . $this->t('You can set the @adjusters and more.', ['@adjusters' => implode(', ', $adjuster_names)]);
    }

    $skip = count($definition['labels']);
    $element['listing'] = $list_builder->overrideEntities($ids, $skip)->render();

    return $element;
  }

}
