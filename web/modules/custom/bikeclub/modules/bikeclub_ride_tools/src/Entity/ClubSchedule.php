<?php

namespace Drupal\bikeclub_ride_tools\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\bikeclub_ride_tools\ClubInterface;

/**
 * Defines the 'club_schedule' entity type.
 * club_schedule date is not included here because the views_year_filter does
 *  not recognize the Drupal date in a custom entity.
 *
 * @ContentEntityType(
 *   id = "club_schedule",
 *   label = @Translation("Club Schedule dates - for join with Ride dates"),
 *   base_table = "club_schedule",
 *   entity_keys = {
 *     "id" = "schedule_id",
 *     "uuid" = "uuid",
 *     "owner" = "author",
 *     "published" = "published",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\bikeclub_ride_tools\ClubScheduleListBuilder",
 *     "views_data" = "Drupal\bikeclub_ride_tools\ClubScheduleViews",
 *     "form" = {
 *       "default" = "Drupal\bikeclub_ride_tools\Form\ClubScheduleForm",
 *     },
 *     "access" = "Drupal\bikeclub_ride_tools\ClubScheduleAccessControlHandler",
 *   },
 *   admin_permission = "administer Club ride schedule",
 *   links = {
 *     "canonical" = "/admin/structure/club_schedule/{club_schedule}",
 *     "edit-form" = "/admin/structure/club_schedule/{club_schedule}/edit",
 *     "collection" = "/admin/structure/club_schedule/list"
 *   },
 *   field_ui_base_route = "bikeclub_ride_tools.schedule_settings",
 * )
 *
 */
class ClubSchedule extends ContentEntityBase implements ClubInterface {

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
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // id  is the unique record ID, assigned as primary index.
    $fields['schedule_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the schedule date entity.'))
      ->setReadOnly(TRUE);

    // Standard field, unique.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the route entity.'))
      ->setReadOnly(TRUE);

    $fields['schedule_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Schedule date'))
      ->setDescription(t('Schedule date'))
      ->setSettings([
        'datetime_type' => 'date',
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'medium',
        ],
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['weekday'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Weekday'))
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Entity reference field, holds the reference to the user object
    $fields['ride_leader'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Ride leader'))
      ->setDescription(t('Enter name and select from list of ride leaders.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', array(
        'include_anonymous' => FALSE,
      ))
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
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

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
