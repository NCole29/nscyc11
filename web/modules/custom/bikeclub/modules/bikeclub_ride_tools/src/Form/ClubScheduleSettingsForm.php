<?php

namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ClubScheduleSettingsForm.
 *
 * @ingroup club_schedule
 */
class ClubScheduleSettingsForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'schedule_settings';
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
    $form['schedule_settings']['#markup'] =
	'Manage fields, form, and display for schedule dates.
	<p>Base fields are not listed on the <a href="/admin/structure/club_schedule/fields">Manage fields</a> tab because they cannot be deleted;
	they are listed on the <a href="/admin/structure/club_schedule/form-display">Manage form display</a> and <a href="/admin/structure/club_schedule/display">Manage display</a> tabs.
	<br/>New fields may be added on the <a href="/admin/structure/club_schedule/fields">Manage fields</a> tab.</p>';

    return $form;
  }

}
