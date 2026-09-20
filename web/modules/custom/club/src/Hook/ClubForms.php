<?php
declare(strict_types=1);

namespace Drupal\club\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormInterface; 
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for forms.
 */
class ClubForms {

 /**
  * Constructor for Hooks.
  */
  public function __construct(
    protected ConfigFactoryInterface $config,
    protected AccountProxyInterface $currentUser,
    protected RouteMatchInterface $routeMatch,
    protected RequestStack $requestStack
  ) {
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(&$form, $form_state, $form_id) {
    
    $entity = $form_state->getFormObject()->getEntity();
    $nodeType = $form_state->getFormObject()->getEntity()->getType();
    $operation = $form_state->getFormObject()->getOperation();
  
    switch ($nodeType) {
      case 'event':
      case 'page':
      case 'webform':

        // Change the label on 'Page is visible to' from "- None -" to "- All -"
        if (isset($form['field_page_access']['widget']['#options']['_none'])) {
          $form['field_page_access']['widget']['#options']['_none'] = "- All -";
        }

        if ($nodeType == 'event' && $operation == 'edit') {
          if($this->pastDate($entity) == 1) {
            $form['custom_message'] = [
              '#type' => 'markup',
              '#markup' => '<div class="messages messages--error">' . 
                t('<h5>You are editing an event that has already occurred. Please exit the form and CLONE the event.</h5>') .
                '</div>',
              '#weight' => -10, 
            ];
          }
        }
        break;

      case 'ride':
      case 'recurring_ride':
        // Change button text.
        $form['field_ride_leader']['widget']['add_more']['#value'] = "Add another leader";
    
        // Disable Route field if RWGPS API key is empty.
        $rwgps = $this->config->get('club.adminsettings')->get('rwgps_api');
        if (!$rwgps) {
          $form['field_rwgps_routes']['#disabled'] = TRUE;
          $form['field_rwgps_routes']['widget']['#title'] = 
            "RWGPS Routes - Please enter a <a href='/admin/config/club/rwgps-api'>RWGPS API key</a> to enable this field. The RideTools module is required.";
        }

        if ($nodeType == 'ride') {
          if ($operation != 'quick_node_clone') {
            $form['field_cancel']['#disabled'] = TRUE;
          }

          if ($operation == 'edit' & $this->pastDate($entity) == 1) {
            $form['custom_message'] = [
              '#type' => 'markup',
              '#markup' => '<div class="messages messages--error">' . 
                t('<h5>You are editing a ride that has already occurred. Please exit the form and CLONE the ride.</h5>') .
                '</div>',
              '#weight' => -10, // Adjust weight to position it
            ];
          } 
        } elseif ($nodeType == 'recurring_ride') {
           $form['field_datetime']['widget']['add_more']['#value'] = "Add another date";
        }
      break;
    }
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, $form_state, $form_id) {
    switch($form_id) {
      case 'user_form':
        // If editing Own account, hide name (it's set in CiviCRM), status, and roles.
        $form_user = $this->routeMatch->getParameter('user')->id();
        $currentUser = $this->currentUser->id();

        if ($form_user == $currentUser) {
          $form['account']['name']['#access'] = FALSE;
          $form['account']['status']['#access'] = FALSE;
          $form['account']['roles']['#access'] = FALSE;
        }
      break;

      case 'user_login_form':
        // Password description text (see core/modules/user/src/form/UserLoginForm.php)
        $form['pass']['#description'] = t('Forgot your <a href="/user/password"><strong>password?</strong></a>');
      break;

      case 'user_pass':
        // Password reset form
        $form['custom_field'] = [
          '#markup' => t('Be sure to check your spam or junk folder. If you do not find an email, contact the
            <a href="/contact/membership"><strong>Membership Coordinator</strong></a>.<br>
            Return to <a href="/user/login"><strong>Log in</strong></a> page.')];
      break;
    }
  }

  public function pastDate($entity) {
    //$now = \Drupal::service('date.formatter')->format(time(), 'custom', 'Y-m-d');
    $date = $entity->get('field_date')->date;
    $now = new DrupalDateTime('now');
    /*
    d($date);
    d($now);
    d($date->format('Y-m-d'));
    d($now->format('Y-m-d'));
    die;
    */
    $pastDate = (!is_null($date->format('Y-m-d')) and $date->format('Y-m-d') < $now->format('Y-m-d')) ;

    return $pastDate;
  }

  #[Hook('form_taxonomy_overview_terms_alter')]
  function positionsFormAlter(array &$form, FormStateInterface &$form_state, $form_id) {

    // Display fields on "positions" taxonomy term listing.
    $path = $this->requestStack->getCurrentRequest()->getPathInfo();

    $arg = explode('/', $path);  // Get vocabulary name from path. 

    // Mailboxes
    if ($arg[5] == "mailboxes") {
      $form['terms']['#header'] = array_merge(array_slice($form['terms']['#header'], 0, 1, TRUE),
        [t('Mailbox')],
        [t('Disabled')],
        array_slice($form['terms']['#header'], 1, NULL, TRUE)
      );

      foreach ($form['terms'] as &$term) {
        if (is_array($term) && !empty($term['#term'])) {

          $disabled = ($term['#term']->get('field_disabled')->value == 1 ) ? "yes" : "-";

          $mailbox['Mailbox'] = [
            '#markup' => $term['#term']->get('field_mailbox')->value,
            '#type' => 'item',
          ];

          $dropped['Disabled'] = [
            '#markup' => $disabled,
            '#type' => 'item',
          ];
        
          $term = array_merge(
            array_slice($term, 0, 1, TRUE),
            $mailbox, $dropped,
            array_slice($term, 1, NULL, TRUE),
          );
        }
      }
    }
  }
}