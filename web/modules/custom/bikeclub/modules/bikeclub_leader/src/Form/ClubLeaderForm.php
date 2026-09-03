<?php

namespace Drupal\bikeclub_leader\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Language\Language;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the club_leader entity edit forms.
 *
 * @ingroup club
 */
class ClubLeaderForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\bikeclub_leader\Entity\ClubLeader */
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
    //$form_state->setRedirect('entity.club_leader.collection');
    $form_state->setRedirect('view.club_leaders.edit');
    $entity = $this->getEntity();
    $entity->save();
  }

}
