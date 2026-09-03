<?php

namespace Drupal\bikeclub_leader\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a club_leader entity.
 *
 * @ingroup club
 */
class ClubLeaderDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

   public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete entity %name?', ['%name' => $this->entity->name->value]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the club_leader list.
   */
  public function getCancelUrl() {
    return new Url('entity.club_leader.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $entity = $this->getEntity();

    // Remove Drupal role associated with the position when club leader is deleted.
    if(!is_null($entity->position->target_id) ) {
      $user = $this->entityTypeManager->getStorage('user')->load($entity->leader->target_id);
      
      $position = $this->entityTypeManager()->getStorage('taxonomy_term')->load($entity->position->target_id);
      $position_role = $position->get('field_website_role')->target_id;

      // Remove Drupal role associated with the position when club leader is deleted.
      if (!empty($position_role) and $user->hasRole($position_role)) {
        $user->removeRole($position_role);
        $user->save();
      }
    }
    $entity->delete();

    $this->messenger()->addStatus($this->t('The Club leader %label has been deleted.', [
      '%label' => $this->entity->name->value,
    ]));
	
    $this->logger('club')->notice('@type: deleted %title.',
      [
        '@type' => $this->entity->bundle(),
        '%title' => $this->entity->name->value,
      ]);
	  
    // Check dev - routes info to get the View route
    $form_state->setRedirect('view.club_leaders.edit');
  }
}
