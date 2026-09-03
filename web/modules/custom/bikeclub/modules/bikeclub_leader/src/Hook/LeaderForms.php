<?php

declare(strict_types=1);

namespace Drupal\bikeclub_leader\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for the bikeclub_leader module.
 */
class LeaderForms {

  /**
   * Constructor for LeaderForms Hooks.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack
  ) {
  }

  /**
   * Implements hook_form_alter().
   *
   */
  #[Hook('form_alter')]
    function leaderFormAlter(array &$form, FormStateInterface &$form_state, $form_id) {
    
      if ($form_id == 'club_leader_form') {

        // Disable Club positions if taxonomy is not filled.
        $positions = $this->entityTypeManager->getStorage('taxonomy_term')
          ->loadByProperties(['vid' => 'positions']);
  
        if (!$positions) {
          $path = '/admin/structure/taxonomy/manage/positions/overview';
          $form['position']['#disabled'] = TRUE;
          $form['position']['widget']['#title'] = "Please fill the <a href=$path>Club positions taxonomy</a> to enable this field.";
        }
        
        // Hide Time portion of the dates. Drupal will use current time.
        $form['start_date']['widget'][0]['value']['#date_time_element'] = 'none';
        $form['start_date']['widget'][0]['value']['#date_time_format'] = '';

        $form['end_date']['widget'][0]['value']['#date_time_element'] = 'none';
        $form['end_date']['widget'][0]['value']['#date_time_format'] = '';

        // Hide 'name' field on Add form (id is missing when adding content).
        if(!$form_state->getformObject()->getEntity()->id()) {
          $form['name']['#access'] = FALSE;
        }
      }
    }

  #[Hook('form_taxonomy_overview_terms_alter')]
  function positionsFormAlter(array &$form, FormStateInterface &$form_state, $form_id) {
  
    // Display fields on "positions" taxonomy term listing.
    $path = $this->requestStack->getCurrentRequest()->getPathInfo();

    $arg = explode('/', $path);  // Get vocabulary name from path. 

    // Club positions
    if ($arg[5] == "positions") {
      $form['terms']['#header'] = array_merge(array_slice($form['terms']['#header'], 0, 1, TRUE),
        [t('Category')],
        [t('Drupal role')],
        [t('Disabled')],
        array_slice($form['terms']['#header'], 1, NULL, TRUE)
      );

      foreach ($form['terms'] as &$term) {
        if (is_array($term) && !empty($term['#term'])) {

          $disabled = ($term['#term']->get('field_disabled')->value == 1 ) ? "yes" : "-";

          $category['Category'] = [
            '#markup' => $term['#term']->get('field_position_category')->value,
            '#type' => 'item',
          ];
          $role['Drupal role'] = [
            '#markup' => ($term['#term']->get('field_website_role')->getValue()) ? $term['#term']->get('field_website_role')->entity->label(): NULL,
            '#type' => 'item',
          ];
          $dropped['Disabled'] = [
            '#markup' => $disabled,
            '#type' => 'item',
          ];
        
          $term = array_merge(
            array_slice($term, 0, 1, TRUE),
            $category, $role, $dropped,
            array_slice($term, 1, NULL, TRUE),
          );
        }
      }
    }
  }
}