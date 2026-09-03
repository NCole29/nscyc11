<?php

namespace Drupal\bikeclub_history\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class OldMemberCounts implements ContainerInjectionInterface  {

  protected $database;
  protected $entityTypeManager;

   public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
   }
   
   public static function create(ContainerInterface $container) {
     return new static(
      $container->get('database'),
      $container->get('entity_type.manager')
    );
   }

  /**
   * {@inheritdoc}
   */
  public function tabulate() {

    // Get first membership year in database
    $query = $this->database->select('civicrm_contribution', 'c');
    $query->condition('c.financial_type_id', 2);
    $query->addExpression('MIN(c.receive_date)', 'receive_date');
    $result = $query->execute()->fetchObject();

    $first_mem_date = $result->receive_date;
    $first_mem_year = date('Y', strtotime($first_mem_date));
    $current_year = date("Y");

    // List of contact_ids with completed membership payments.
    $cids = $this->database->query("SELECT DISTINCT(contact_id) FROM {civicrm_contribution} 
      WHERE financial_type_id = :finId and contribution_status_id = :status
      ORDER BY contact_id", 
      [':finId' => 2, 
      ':status' => 1, 
      ])
      ->fetchALL();

    /* --------------------
     * LOOP OVER CONTACTS  
     * --------------------*/
    foreach ($cids as $contact) {
      $cid = $contact->contact_id;
      $lastMemberYr = 0; // Initialize per Contact.

      //Initialize array of member by year. 
      for ($year = $first_mem_year; $year < $current_year; $year++) {
        $members[$year][$cid] = 0; 
      }

      // Retrieve contributions for membership with status = completed, is_test =0, and term not null.
      // Don't exclude deleted contacts. If they have contributions, they were no merged properly and those
      // contributions reflect valid memberships.
      $query = $this->database->select('civicrm_contribution', 'c');
      $query->leftjoin('civicrm_contribution__field_membership_term', 'mt', 'c.id = mt.entity_id');
      $query
        ->fields('c', ['id','receive_date','total_amount'])
        ->fields('mt', ['field_membership_term_value'])
        ->condition('c.contact_id', $cid, '=')
        ->condition('c.financial_type_id', 2, '=')
        ->condition('c.is_test', 0, '=')
        ->condition('c.contribution_status_id', 1, '=')
        ->condition('mt.field_membership_term_value', NULL, 'IS NOT NULL');
      $contributions = $query->execute()->fetchAll(); 

      foreach ($contributions as $contribution) {
        $term = $contribution->field_membership_term_value;
        $paymentYr = date('Y', strtotime($contribution->receive_date));
        
        // For each member, fill array of membership years. 
        if ($paymentYr > $lastMemberYr) {
          $startYr = $paymentYr;
        } else {
          $startYr = $lastMemberYr + 1;
        }

        for($year = $startYr; $year < ($startYr + $term); $year++){
          $members[$year][$cid] = 1;
         
          $lastMemberYr = $year;
        }
      } // End loop over contributions.
    } // End loop over Contacts


    // Sum members for each year and save.
    $today = time();
    $node_storage = $this->entityTypeManager->getStorage('node');

    for ($year = $first_mem_year; $year < $current_year; $year++) {
      $counts[$year]['year'] = $year;
      $counts[$year]['members'] = array_sum($members[$year]);  
      $category = 'membership';

      // Check for existing record and update or insert new record.
      // loadByProperties returns an array of nodes, even if only one is returned.
      $node = $node_storage->loadByProperties([
        'type' =>'statistic',
        'field_year' => $year,
        'field_category' => $category
      ]);

      if (!empty($node)) {
        foreach ($nodes as $node) {
          $node->set('field_number', $counts[$year]['members']);
          $node->save();
        }
      } 
      else {
        $node = $node_storage->create([
          'type' => 'statistic',
          'title' => $year . ' ' . $category,
          'field_year' => $year,
          'field_category' => $category,
          'field_number' => $counts[$year]['members'],
          'status' => 1,
          'created' => $today,
          'changed' => $today,
        ]);
        $node->save();  
      }
    }

    $key_values = array_column($counts, 'year'); 
    array_multisort($key_values, SORT_DESC, $counts);

    // Build table
    $title = "<h3 class='w3-block-title'>Membership by Year</h3>";
    $header = ['Year', 'Members'];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $counts,
      '#empty' => 'No content has been found.',
      '#attributes' => array (
        'class' => ['number-table','w3-medium','narrow-table'],
      ),
      '#cache' => array (
        'max-age' => 0,
      ),
    ];
    $tableHTML = \Drupal::service('renderer')->renderPlain($build);
    return [
      '#type' => '#markup',
      '#markup' => $title . $tableHTML,
    ];
  }
}

