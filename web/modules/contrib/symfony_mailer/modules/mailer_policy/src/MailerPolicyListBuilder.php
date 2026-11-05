<?php

declare(strict_types=1);

namespace Drupal\mailer_policy;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Defines a class to build a listing of mailer policy entities.
 *
 * @todo Add filters by type and by adjuster.
 */
class MailerPolicyListBuilder extends ConfigEntityListBuilder implements MailerPolicyListBuilderInterface {

  /**
   * Overridden list of entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $overrideEntities;

  /**
   * Number of levels to skip when displaying the tag.
   */
  protected int $skip = 0;

  /**
   * The columns to hide.
   *
   * @var string[]
   */
  protected array $hideColumns = [];

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'tag' => $this->t('Tag'),
      'entity' => $this->t('Entity'),
      'summary' => $this->t('Summary'),
    ];
    return array_diff_key($header, $this->hideColumns) + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $summary['data']['#markup'] = $entity->getSummary(!empty($this->overrideEntities));
    $row = [
      'tag' => $entity->getTagLabel($this->skip),
      'entity' => $entity->getEntityLabel(),
      'summary' => $summary,
    ];
    return array_diff_key($row, $this->hideColumns) + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    return $this->overrideEntities ?? parent::load();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    if ($entity->isNew()) {
      $operations['create'] = [
        'title' => $this->t('Create'),
        'weight' => -10,
        'url' => $this->ensureDestination(Url::fromRoute('entity.mailer_policy.add_id_form', ['policy_id' => $entity->id()])),
      ];
    }
    else {
      $operations = parent::getDefaultOperations($entity);
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function overrideEntities(array $entity_ids, int $skip = 0): static {
    $this->skip = $skip;
    foreach ($entity_ids as $policy_id) {
      $this->overrideEntities[] = MailerPolicy::loadOrCreate($policy_id);
    }
    uasort($this->overrideEntities, [$this->entityType->getClass(), 'sortSpecific']);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hideColumns(array $columns): static {
    $this->hideColumns = array_flip($columns);
    return $this;
  }

}
