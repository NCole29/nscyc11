<?php

namespace Drupal\bikeclub_ride_tools;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for ClubRoute entity.
 *
 * @ingroup bikeclub_ride
 */
class ClubRouteListBuilder extends EntityListBuilder {

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs a new ClubRouteListBuilder object - Pass 3 arguments to createInstance().
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator) {
    parent::__construct($entity_type, $storage);

    $this->urlGenerator = $url_generator;
  }

  /*
   * We extend EntityListBuilder which has createInstance, but we need it here because we add url_generator to the container
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('url_generator')
    );
  }

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('<p>Use Operations column to edit/delete route record.</p>',
       ['@adminlink' => $this->urlGenerator->generateFromRoute('bikeclub_ride_tools.route_settings'),
      ]),
    ];
    $build['table'] = parent::render();

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the route list.
   *
   * Calling the parent::buildHeader() adds a column for operations
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    //$header['route_id'] = $this->t('Route ID');

	$header = [
	  'rwgps_id' => $this->t('RWGPS route #'),
    'name' => $this->t('Route name'),
	  'distance' => $this->t('Distance'),
	  'elev_gain' => $this->t('Elevation gain'),
	  ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    $row['rwgps_id'] = $entity->rwgps_id->value;
    $row['name'] = $entity->name->value;
    $row['distance'] = $entity->distance->value;
    $row['elev_gain'] = $entity->elevation_gain->value;

    return $row + parent::buildRow($entity);
  }
}
