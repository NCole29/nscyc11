<?php

namespace Drupal\bikeclub_leader\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ClubLeaderSettingsForm.
 *
 * @ingroup club
 */
class ClubLeaderSettingsForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'club_leader_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Empty implementation of the abstract submit class.
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['club_leader_settings']['#markup'] =
    'Manage fields, form, and display for club leaders.
    <p>Base fields are not listed on the <a href="/admin/structure/club_leader/fields">Manage fields</a> tab because they cannot be deleted;
    they are listed on the <a href="/admin/structure/club_leader/form-display">Manage form display</a> and <a href="/admin/structure/club_leader/display">Manage display</a> tabs.
    <br/>New fields may be added on the <a href="/admin/structure/club_leader/fields">Manage fields</a> tab.</p>';
  
    return $form;
  }

}
