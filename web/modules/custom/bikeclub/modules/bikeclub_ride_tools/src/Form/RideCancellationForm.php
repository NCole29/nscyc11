<?php
/**
 * @file
 * Contains \Drupal\bikeclub_ride_tools\Form\RideCancellationForm.
 */
namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface; 
use Symfony\Component\DependencyInjection\ContainerInterface;

class RideCancellationForm extends FormBase {

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
    return 'cancel_ride_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $roles = $this->currentUser->getRoles();

    // Applies to all forms.
    $form['instructions'] = [
      '#type' => 'markup',
      '#markup' => "<h4>Rides scheduled within the <u>next</u> two weeks may be cancelled.</h4>"
      . "<strong>** Cancelled **</strong> immediately appears on calendar displays."
      . "<br>The cancellation notice (top of the home page) displays until 4 hours after the ride start, and no earlier than 4 days before the ride start."
       . "<p>To <strong>undo</strong> a cancellation, select the ride and uncheck the cancel box.</p>"
    ];
      $form['layout'] = [
      '#type' => 'fieldset',
    ];

    // Admin - 1 form field to select ride or recurring ride.
    if (in_array('administrator',$roles) or in_array('rides_coordinator',$roles)) {
      $display = 'all_next_rides';

      $form['layout']['ride'] = [
        '#type' => 'entity_autocomplete',
        '#title' => t('Ride name'),
        '#description' => t('Enter part of the ride name and select from list.'),
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
    // Ride leaders - 2 form fields for rides and recurring (due to join of recurring_dates with recurring_rides).
    else if(in_array('ride_leader',$roles)) {
      $display1 = 'own_next_rides'; 
      $display2 = 'own_next_recurring'; 

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

    // Applies to all forms.
    $form['layout']['cancel'] = [
      '#type' => 'checkbox',
      '#title' => t('Cancel'),
      '#default_value' => 1,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
   }
  
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Leave empty. Default check that one and only one field was filled.
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getValue('ride')) {
      $node = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('ride'));
    } else {
      $node = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('recur_ride'));
    }
    $cancel = $form_state->getValue('cancel');

    $node->set('field_cancel', $cancel);
    $node->save();

    $rideName = $node->getTitle();

    if ($cancel == 1) {
      $this->messenger()->addMessage(t("$rideName has been cancelled"));
    } else {
      $this->messenger()->addMessage(t("$rideName cancellation is undone"));
    }
    $form_state->setRedirect('<front>');
  }
}