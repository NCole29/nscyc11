<?php

namespace Drupal\bikeclub_ride_tools\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;
use Drupal\bikeclub_ride_tools\ClubInterface;

/**
 * Defines the 'club_route' entity type.
 *
 * @ContentEntityType(
 *   id = "club_route",
 *   label = @Translation("Club Routes - Data from RWGPS"),
 *   base_table = "club_route",
 *   entity_keys = {
 *     "id" = "rwgps_id",
 *     "uuid" = "uuid",
 *     "label" = "rwgps_id",
 *     "owner" = "author",
 *     "published" = "published",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\bikeclub_ride_tools\ClubRouteListBuilder",
 *     "views_data" = "Drupal\bikeclub_ride_tools\ClubRouteViews",
 *     "form" = {
 *       "default" = "Drupal\bikeclub_ride_tools\Form\ClubRouteForm",
 *       "delete" = "Drupal\bikeclub_ride_tools\Form\ClubRouteDeleteForm",
 *       "edit" = "Drupal\bikeclub_ride_tools\Form\ClubRouteForm",
 *     },
 *     "access" = "Drupal\bikeclub_ride_tools\ClubRouteAccessControlHandler",
 *   },
 *   admin_permission = "administer RWGPS route fields",
 *   links = {
 *     "canonical" = "/admin/structure/club_route/{club_route}",
 *     "edit-form" = "/admin/structure/club_route/{club_route}/edit",
 *     "delete-form" = "/admin/structure/club_route/{club_route}/delete",
 *     "collection" = "/admin/structure/club_route/list"
 *   },
 *   field_ui_base_route = "bikeclub_ride_tools.route_settings",
 * )
 *
 */
class ClubRoute extends ContentEntityBase implements ClubInterface {

  use EntityChangedTrait, EntityOwnerTrait, EntityPublishedTrait;
  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the user_id entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }
  
  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Route_number is the unique RWGPS ID, assigned as primary index.
    $fields['rwgps_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('RWGPS ID'))
      ->setDescription(t('The RWGPS route number.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Standard field, unique.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the route entity.'))
      ->setReadOnly(TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Route name'))
      ->setDescription(t('The name of the route.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['distance'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Distance'))
      ->setDescription(t('The distance of the route.'))
      ->setDisplayOptions('view', array(
          'label' => 'inline',
          'type' => 'integer',
          'weight' => 2,
        ))
      ->setDisplayOptions('form', array(
          'type' => 'number',
          'weight' => 2,
        ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['elevation_gain'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Elevation'))
      ->setDescription(t('The elevation gain.'))
      ->setDisplayOptions('view', array(
          'label' => 'inline',
          'type' => 'integer',
          'weight' => 3,
        ))
      ->setDisplayOptions('form', array(
          'type' => 'number',
          'weight' => 3,
        ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['unpaved'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Unpaved pct'))
      ->setDescription(t('RWGPS unpaved percent'))
      ->setSettings([ 
      'max_length' => 10, 
      'suffix' => '%', 
      ]) 
      ->setDefaultValue('0')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'integer',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['terrain'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Terrain'))
      ->setDescription(t('RWGPS terrain'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'integer',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
      
    $fields['surface'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Surface'))
      ->setDescription(t('RWGPS surface'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'integer',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);  

    $fields['locality'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Locality'))
      ->setDescription(t('City or town.'))
      ->setSettings([
         'default_value' => '',
         'max_length' => 50,
        ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // RWGPS administrative area is U.S. State and Canadian province
    $fields['state'] = BaseFieldDefinition::create('string')
      ->setLabel(t('State'))
      ->setDescription(t('State or province.'))
      ->setSettings([
         'default_value' => '',
         'max_length' => 50,
        ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['geofield'] = BaseFieldDefinition::create('geofield')
      ->setLabel(t('Geofield for mapping'))
      ->setDescription(t('Automatically filled from lat/lng fields.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('view', array(
        'weight' => 8,
      ))
      ->setDisplayOptions('form', array(
        'weight' => 8,
      ));

    // Entity reference field, holds the reference to the user object
    $fields['ride_start'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Ride start'))
      ->setDescription(t('References the Location content type.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'location' => 'location',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => -4,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Created on RWGPS'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['updated_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Updated on RWGPS'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    // Owner field of the contact.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ))
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The language code of ContentEntityExample entity.'));
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

     $fields += static::publishedBaseFieldDefinitions($entity_type);

    return $fields;
  }
}
