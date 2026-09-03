<?php

namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;


/**
 * Form controller for the club_route entity edit forms.
 *
 * @ingroup bikeclub_ride_tools
 */
class ClubRouteForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $form['langcode'] = [
      '#title' => $this->t('Language'),
      '#type' => 'language_select',
      '#default_value' => $entity->getUntranslated()->language()->getId(),
      '#languages' => Language::STATE_ALL,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    //$form_state->setRedirect('entity.club_route.collection');
    $form_state->setRedirect('view.club_routes.admin');
    $entity = $this->getEntity();
    $entity->save();
  }

}
