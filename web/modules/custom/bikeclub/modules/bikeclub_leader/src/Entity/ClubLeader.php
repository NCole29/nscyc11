<?php

namespace Drupal\bikeclub_leader\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;
use Drupal\bikeclub_leader\ClubLeaderInterface;
use Drupal\bikeclub_leader\Utility\ClubLeaderCleanup;


/**
 * Defines the 'club_leader' entity.
 *
 * @ContentEntityType(
 *   id = "club_leader",
 *   label = @Translation("Club Leaders - Officers, Coordinators, Directors"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\bikeclub_leader\ClubLeaderViews",
 *     "form" = {
 *       "default" = "Drupal\bikeclub_leader\Form\ClubLeaderForm",
 *       "delete" = "Drupal\bikeclub_leader\Form\ClubLeaderDeleteForm",
 *     },
 *     "access" = "Drupal\bikeclub_leader\ClubLeaderAccessControlHandler",
 *   },
 *   base_table = "club_leader",
 *   admin_permission = "administer club leader",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "published" = "published"
 *   },
 *   links = {
 *     "edit-form" =   "/admin/structure/club_leader/{club_leader}/edit",
 *     "delete-form" = "/admin/structure/club_leader/{club_leader}/delete"
 *   },
 *   field_ui_base_route = "bikeclub_leader.leader_settings",
 * )
 *
 */
class ClubLeader extends ContentEntityBase implements ClubLeaderInterface {

  use EntityChangedTrait, EntityOwnerTrait,EntityPublishedTrait;

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the uid entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
  }

    /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Sequential record ID'))
      ->setDescription(t('The ID of the Club leader entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Club leader entity.'))
      ->setReadOnly(TRUE);

    $fields['fid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Feed ID'))
      ->setDescription(t('Unique ID for feeds import.'))
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    // Leader - entity reference field with autocomplete form.
    $fields['leader'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Leader'))
      ->setDescription(t("Enter name and select from list.<br>By default, selection is limited to persons with roles in addition to 'authenticated user'. To limit to specific roles 
      (e.g., members) edit the <a href='/admin/structure/views'>Club eligible leaders</a> view."))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'views')
      ->setSetting('handler_settings', [
        'view' => [
          'view_name' => 'club_leaders_eligible',
          'display_name' => 'entity_reference',
          'arguments' => [],
        ]
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
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Leader name, saved or uploaded (via feed) as text.
    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Uploaded or edited name'))
      ->setDescription(t('Name: uploaded or autofilled.'))
      ->setSettings([
        'max_length' => 50,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -3,
          ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

	$fields['start_date'] = BaseFieldDefinition::create('timestamp')
	  ->setLabel(t('Start date'))
	  ->setDescription('Start of term.')
	  ->setRequired(TRUE)
	  ->setDisplayOptions('view', [
		  'label' => 'inline',
		  'type' => 'timestamp',
		  'weight' => -2, 
	  ])  
	  ->setDisplayOptions('form', [
		  'type' => 'datetime_timestamp',
		  'weight' => -2, 
	  ])
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

	$fields['end_date'] = BaseFieldDefinition::create('timestamp')
	  ->setLabel(t('End date'))
	  ->setDescription('End of term.')
    ->setDisplayOptions('view', [
		  'label' => 'inline',
		  'type' => 'timestamp',
		  'weight' => -1, 
	  ])  
	  ->setDisplayOptions('form', [
		  'type' => 'datetime_timestamp',
		  'weight' => -2, 
	  ])
    ->setDisplayConfigurable('view', TRUE)
    ->setDisplayConfigurable('form', TRUE);

    // Club position taxonomy term.
    $fields['position'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Club position'))
      ->setDescription(t('Officer or coordinator position.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'views')
      ->setSetting('handler_settings', [
        'view' => [
          'view_name' => 'club_leaders_positions',
          'display_name' => 'not_eliminated',
          'arguments' => [],
        ]
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'inline',
        'type' => 'string',
        'weight' => 1,
        ))
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => 1,
          ))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    // Year of start date
    $fields['year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Year'))
      ->setDescription(t('Year of start date.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'integer',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
            
    // Owner field of the contact.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Submitted by'))
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

  /**
   * {@inheritdoc}
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalData() {
	    return $this->originalData;
	}

  /**
   * {@inheritdoc}
   */
  public function setOriginalData(array $data) {
    $this->originalData = $data;
  }
  
   /**
   * {@inheritdoc}
   */
  public function getRole($position) {
    $term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($position);
    $this->role = $term->get('field_website_role')->target_id;
	  return $this->role;
  }
  
  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    
    // Check if position is changing, if yes, and ROLE is changing then remove orig role.
    // Execute on edit not add, so check for original record ID.
    if ($this->original) {
      if ($this->position->target_id <> $this->original->position->target_id) {

        $user = $this->entityTypeManager->getStorage('user')->load($this->original->leader->target_id);
        $orig_role = $this->getRole($this->original->position->target_id);
        $new_role = $this->getRole($this->position->target_id);
    
        // Remove Drupal role associated with original position when club position is changed.
        if ($new_role <> $orig_role & !empty($orig_role) & $user->hasRole($orig_role)) {
          $user->removeRole($orig_role);
          $user->save();
        }
      }
    } 
  }
  
  /**
   * {@inheritdoc}
   */
  public function save() {
    // Fill Year of Start date.
    $year = date("Y", $this->start_date->value);
    $this->set('year', $year);

    // Fill name if leader is Drupal user and name field is empty (don't overwrite contents).
    if ( !empty($this->leader) and ($this->name->value == NULL)) {
      $this->name = $this->leader->entity->label();
    }

    // If leader is Drupal user, assign or remove website ROLE associated with position (see taxonomy).
    if ( !empty($this->leader) & !is_null($this->position)) {
	    $position_role = $this->getRole($this->position->target_id);
      $user = $this->entityTypeManager->getStorage('user')->load($this->leader->target_id);

      $today = strtotime(date('d-m-Y'));
      $isCurrent = ($this->end_date->value > $today);

      // Add or delete role depending on isCurrent.
      if ($isCurrent & !$user->hasRole($position_role)) {
        $user->addRole($position_role);
      } elseif (!$isCurrent & $user->hasRole($position_role)) {
        $user->removeRole($position_role);
      } 
      $user->save();
      parent::save();
      $cleanup = new ClubLeaderCleanup;
      $cleanup->roleCleanup();
    }
  }  
}
