<?php

namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a club entity.
 *
 * @ingroup bikeclub_ride_tools
 */
class ClubRouteDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete entity %name?', ['%name' => $this->entity->name->value]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the route list.
   */
  public function getCancelUrl() {
    //return new Url('entity.club_route.collection');
    return new Url('view.club_routes.admin');
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
    $entity->delete();

    $this->logger('club')->notice('@type: deleted %title.',
      [
        '@type' => $this->entity->bundle(),
        '%title' => $this->entity->label(),
      ]);
    //$form_state->setRedirect('entity.club_route.collection');
      $form_state->setRedirect('view.club_routes.admin');
  }

}
