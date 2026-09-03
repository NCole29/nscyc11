<?php

namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface; 
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

class NonmemberForm extends FormBase {

  protected AccountInterface $currentUser;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(AccountInterface $currentUser, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nonmember_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['instructions'] = [
      '#type' => 'markup',
      '#markup' => "<h4>Rides scheduled within the <u>last 5 days</u> may be selected.</h4>",
    ];
    $form['layout'] = [
      '#type' => 'fieldset',
    ];

    if($this->currentUser->hasPermission('enter waivers')) {
      // Admin - one form field to select any past ride.
      if ($this->currentUser->hasRole('rides_coordinator') |
          $this->currentUser->hasRole('site_admin') |
          $this->currentUser->hasRole('administrator') ) {

        $display = 'all_past_rides';

        $form['layout']['ride'] = [
          '#type' => 'entity_autocomplete',
          '#title' => t('Ride name'),
          '#description' => t('Enter part of the ride name and select from list. Recurring rides appear first, alphabetically, followed by non-recurring rides.'),
          '#target_type' => 'node',
          '#required' => TRUE,
          '#selection_handler' => 'views',
          '#selection_settings' => [
            'view' => [
              'view_name' => 'ride_dates',
              'display_name' => $display,
              'arguments' => [],
            ],
          ],
        ];
      }
      else if($this->currentUser->hasRole('ride_leader')) {
        // Ride leaders - two form fields for rides and recurring 
        // (due to join of recurring_dates with recurring_rides along with filter on ride leader).
  
        $display1 = 'own_past_rides'; 
        $display2 = 'own_past_recurring'; 

        $form['layout']['select_one'] = [
          '#type' => 'markup',
          '#markup' => "<p>Select one ride. Separate fields are needed due to how recurring dates are stored.",
        ];
        $form['layout']['ride'] = [
          '#type' => 'entity_autocomplete',
          '#title' => t('Non-recurring ride'),
          '#description' => t('Enter part of the ride name and select from list.'),
          '#target_type' => 'node',
          '#required' => FALSE,
          '#selection_handler' => 'views',
          '#selection_settings' => [
            'view' => [
              'view_name' => 'ride_dates',
              'display_name' => $display1,
              'arguments' => [],
            ],
          ],
        ];
        $form['layout']['recur_ride'] = [
          '#type' => 'entity_autocomplete',
          '#title' => t('Recurring ride'),
          '#description' => t('Enter part of the ride name and select from list.'),
          '#target_type' => 'node',
          '#required' => FALSE,
          '#selection_handler' => 'views',
          '#selection_settings' => [
            'view' => [
              'view_name' => 'ride_dates',
              'display_name' => $display2,
              'arguments' => [],
            ],
          ],
        ];
      }
    }

    // Applies to all forms.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Leave empty; default check that one and only one field is filled.
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
   $node_storage = $this->entityTypeManager->getStorage('node');

    if ($form_state->getValue('ride')) {
      $node = $node_storage->load($form_state->getValue('ride'));
    } else {
      $node = $node_storage->load($form_state->getValue('recur_ride'));
    }

    $ride_name = $node->getTitle();
    $ride_date = $node->field_date->value;
    
																							  

    $url = \Drupal\core\Url::fromUserInput("/nonmembers?ride=$ride_name&ride_date=$ride_date");
    $form_state->setRedirectUrl($url);
  }
}