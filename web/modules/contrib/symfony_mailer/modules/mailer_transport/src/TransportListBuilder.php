<?php

declare(strict_types=1);

namespace Drupal\mailer_transport;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of mailer transport entities.
 */
class TransportListBuilder extends ConfigEntityListBuilder {

  /**
   * Constructs a new TransportListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   *
   * @internal
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'plugin' => $this->t('Type'),
      'label' => $this->t('Label'),
      'default' => '',
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $definition = $entity->getPlugin()->getPluginDefinition();
    $row['label'] = $definition['label'];
    $row['plugin'] = $entity->label();
    $row['default'] = $entity->isDefault() ? $this->t('Default') : '';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    // Prevent the default transport being deleted.
    if ($entity->isDefault()) {
      unset($operations['delete']);
    }
    else {
      $operations['default'] = [
        'title' => $this->t('Set as default'),
        'url' => Url::fromRoute('entity.mailer_transport.set_default', [
          'mailer_transport' => $entity->id(),
        ]),
        'weight' => 50,
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build['transport_add_form'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $build['transport_add_form'] += $this->formBuilder->getForm('Drupal\mailer_transport\Form\TransportAddButtonForm');
    $build['transport_table'] = parent::render();
    return $build;
  }

}
