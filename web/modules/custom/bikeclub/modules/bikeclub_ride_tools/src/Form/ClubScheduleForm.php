<?php

namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;


/**
 * Form controller for the club_schedule entity edit forms.
 *
 * @ingroup club_schedule
 */
class ClubScheduleForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $entity = $this->entity;

    $form = parent::buildForm($form, $form_state);

    $form['intro_markup'] = [
      '#type' => 'markup',
      '#weight' => -10,
      '#markup' => $this->t('<h3>Add ride leader to the schedule.</h3>'),
    ];  

    $form['schedule_date']['#disabled'] = TRUE;
    $form['weekday']['#type'] = 'hidden';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
 
    $entity = $this->getEntity();
    $entity->save();
  }
}
