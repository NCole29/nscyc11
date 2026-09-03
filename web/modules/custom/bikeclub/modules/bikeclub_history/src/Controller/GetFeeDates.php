<?php

namespace Drupal\bikeclub_history\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal Views will not aggregate CiviCRM dates to get min & max,
 *  so we do this in code and display on a page.
 */

class GetFeeDates implements ContainerInjectionInterface  {

  protected $database;

   public function __construct(Connection $database) {
     $this->database = $database;
   }
   
   public static function create(ContainerInterface $container) {
     return new static(
      $container->get('database'),
    );
   }

  /**
   * {@inheritdoc}
   */
  public function getDates() {

    // Get min and max dates associated with each membership fee paid.
    $query = $this->database->select('civicrm_contribution', 'c');
    $query->fields('c', ['total_amount']);
    $query->condition('c.financial_type_id', 2);
    $query->condition('c.contribution_status_id', 1);
    $query->addExpression('MIN(c.receive_date)', 'min_date');
    $query->addExpression('MAX(c.receive_date)', 'max_date');
    $query->groupBy('c.total_amount');
    $result = $query->execute()->fetchAll();

    foreach ($result as $result) {
      $fee = $result->total_amount;
      
      $fees[$fee]['fee'] = $result->total_amount;
      // Convert Y-m-d to m-d-Y
      $fees[$fee]['min_date'] = substr($result->min_date,5,2) . '-' . substr($result->min_date,8,2) . '-' . substr($result->min_date,0,4);
      $fees[$fee]['max_date'] = substr($result->max_date,5,2) . '-' . substr($result->max_date,8,2) . '-' . substr($result->max_date,0,4);
    }

    $key_values = array_column($fees, 'min_date'); 
    array_multisort($key_values, SORT_DESC, $fees);


    // Build table
    $title = "<h3 class='w3-block-title'>Membership fees and dates</h3>";
    $help = "If dates overlap, its likely due to a manual entry. Example: Membership fee increased from $15 to $20.  
    The last effective date for $15 should be set to the day before the first date that $20 appears. 
    Memberships paying $15 after that date will not be assigned a membership term, but those can be manually 
    set in the next step of the process.";
    $header = ['Fee', 'First date', 'Last date'];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $help,
      '#rows' => $fees,
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

