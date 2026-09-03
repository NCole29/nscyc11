<?php
declare(strict_types=1);

namespace Drupal\bikeclub\Hook;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormInterface; 
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\views\ViewExecutableFactory;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;

/**
 * Hook implementations for forms.
 */
class BikeclubWebforms {

 /**
  * Constructor for BikeclubWebform Hooks.
  */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
    protected AccountProxyInterface $currentUser,
    protected ViewExecutableFactory $view_executable
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, $form_state, $form_id) {
    //\Drupal::messenger()->addStatus('Form ID: ' . $form_id);

    switch ($form_id) {
      // Shorten the default "delete confirmation" message to make if "friendlier" for users.
      case 'webform_submission_free_event_delete_form':
        $title = $form['#title']->getArguments('%label');  // Get event title and remove "Submission #XXX".
        $event_name = explode(':',reset($title));
        $form['#title'] = 'Cancel registration for ' . $event_name[0];

        $form['warning']['#message_type'] = NULL;  // Remove type to remove the "Warning message" header.
        $form['warning']['#message_message'] = 'Are you sure you want to delete registration for <strong>' . $event_name[0] . '</strong>?'; 
        $form['description'] = NULL;
      break;

      // Warn if renewing membership and user is not logged-in user (can only happen for admins).
      case 'webform_submission_renew_membership_add_form':
        $user = $form['elements']['contact_pagebreak']['civicrm_1_contact_1_contact_existing']['#value'];

        if ($this->currentUser->id() != $user and $this->getCurrentAdmin() == 1) {
          $this->messenger->addError('Form contains YOUR information. Use CiviCRM to manually renew membership for another user.');
        }
      break;
    }

    // Ride registration - populate the form with source_entity info: Ride name and date.
    if ($form_state->getFormObject() instanceof WebformSubmissionForm) {
      $webform_id = $form_state->getFormObject()->getWebform()->id();

      if ($webform_id == 'ride_registration') {
        $source_entity = $form_state->getFormObject()->getEntity()->getSourceEntity();

        // If webform attached to a node.
        if ($source_entity && $source_entity->getEntityTypeId() === 'node') {
          $nid = $source_entity->id();
          $node = $this->entityTypeManager->getStorage('node')->load($nid);

          // "Ride" date is singular, get it from the node.
          if ($node->bundle() == 'ride') {
            $date_string = $node->get('field_date')->value; 
          }
          // "Recurring ride" has multiple dates - get NEXT date from the "ride_dates" View.
          elseif ($node->bundle() == 'recurring_ride') {
            $date_view = $this->entityTypeManager->getStorage('view')->load('ride_dates');
            $view = $this->view_executable->get($date_view);
            $view->setDisplay('register_next_recurring');
            $view->setArguments([$nid,]);
            $view->preExecute();
            $view->execute();
              
            $date_string = $view->result[0]->_entity->get('field_date')->value;
          }

          // Transform UTC date_string (UTC) to default timezone.
          $timezone = new \DateTimeZone('UTC');
          $date_time = new DrupalDateTime($date_string, $timezone);
          $timestamp = $date_time->getTimestamp();

          // $ride_date is displayed on form. $date is saved to database in format to enable sorting.
          $ride_date = \Drupal::service('date.formatter')->format($timestamp, 'custom', 'l, F j, Y \a\t g:i a');
          $date = \Drupal::service('date.formatter')->format($timestamp, 'custom', 'Y-m-d');

          $form['elements']['ride_date']['#default_value'] = $ride_date;
          $form['elements']['date']['#default_value'] = $date;
        }
      }
    }
  }

  /**
   * Function called by hook_form_alter().
   */
  public function getCurrentAdmin() {
    $roles = $this->currentUser->getRoles();

    $currentAdmin = 0;
    if(in_array('administrator',$roles) | in_array('site_admin',$roles) | in_array('membership_coordinator',$roles)) {
      $currentAdmin = 1;
    }
    return $currentAdmin;
  }

  /**
   * Implements hook_webform_submission_presave().
   */
  public function webformSubmission_presave(Drupal\webform\WebformSubmissionInterface $webform_submission) {
    // Unset "ride_date" as its used for display only; "date" is saved to the database.
    $field = 'ride_date';

    $data = $webform_submission->getData();
    if (isset($data[$field])) {
      unset($data[$field]);
      $webform_submission->setData($data);
    }
  }
}