<?php

namespace Drupal\bikeclub_history\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add membership term (Drupal field) to CiviCRM membership contribution records
 *  based on current and historic membership fee data.
 */
class OldMemberTerms implements ContainerInjectionInterface  {

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
   * Retrieve historic membership fees from webform submission.
   */
  public function getOldFees() {

    $webform_id = 'old_membership_fees';
    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');

    $query = $submission_storage->getQuery()
      ->condition('webform_id', $webform_id)
      ->accessCheck(FALSE);
    $result = $query->execute();
    kint($result);
    die;

    // Use the last submission since past submission is pre-filled for updates. 
    $lastresult = max($result);
    $submission = \Drupal\webform\Entity\WebformSubmission::load($lastresult);
    $data = $submission->getData();

    $fees = $data['membership_fees']; // Composite form element.

    if (!is_null($fees)) {
      // Fill array to get one record per fee with fee as the array key.
      foreach ($fees as $fee) {
        $amount = $fee['fee'];
        $oldfees[(int)$amount]["term"] = $fee['term'];
        $oldfees[(int)$amount]["date"] = $fee['date'];
      }
    }
    return $oldfees;
  }

  /**
   * Read current membership fees from CiviCRM.
   */
  public function getFees() {
    
    $query = $this->database->select('civicrm_membership_type', 'm');
    $query
      ->fields( 'm', ['minimum_fee', 'duration_unit', 'duration_interval' ])
      ->condition('m.financial_type_id', 2)
      ->condition('m.is_active', 1);
    $mfees = $query->execute()->fetchAll(); 
  
    foreach($mfees as $mfee) {
      if ($mfee->duration_unit == 'year') {
        $fees[(int)$mfee->minimum_fee] = $mfee->duration_interval + 0;
      }
    }
    return $fees;
  }

  /**
   * Assign membership term to contribution records.
   * Config for field_membership term is imported with club_report module.
   */
  public function addTerm() {

    $oldfees = $this->getOldFees();
    $fees = $this->getFees();

    if ($oldfees) {
      $dates = array_column($oldfees,'date');
      $maxOldDate = max($dates);
    }

    // Get list of contribution Ids, then loop over each one.
    $query = \Drupal::entityQuery('civicrm_contribution')
      ->condition('financial_type_id', 2)
      ->range(0, 9999999999);
    $contributions = $query->execute();
   
    foreach($contributions as $contrib_id) {
    
      // Use database queries due to potential high volume of records to process.
      $query = $this->database->select('civicrm_contribution', 'c');
      $query->leftjoin('civicrm_contribution__field_membership_term', 'mt', 'c.id = mt.entity_id');
      $query
        ->fields('c', ['id','total_amount','receive_date'])
        ->fields('mt', ['field_membership_term_value'])
        ->condition('c.financial_type_id', 2)
        ->condition('c.id', $contrib_id);
      $contribution = $query->execute()->fetch(); 

      $pay_date = date('Y-m-d', strtotime($contribution->receive_date));
      $pay_amount = $contribution->total_amount;

      $term = NULL; // Initialize.

      // Pay date > last OLD fee. Use current fees to assign term.
      if ($pay_date > $maxOldDate and array_key_exists((int)$pay_amount, $fees)) {
        $term = $fees[(int)$pay_amount];  
      } 
      // Pay date <= last OLD fee. Use old fees to assign term.
      elseif (array_key_exists((int)$pay_amount, $oldfees) and $pay_date <= $oldfees[(int)$pay_amount]['date']) {
        $term = $oldfees[(int)$pay_amount]['term']; 
      }

      // INSERT term, or fill array to display records with missing term.
      if (isset($term) and !is_null($term)) {

        if (isset($contribution->field_membership_term_value)) {
          // Membership_term exists, update.
          $this->database->update('civicrm_contribution__field_membership_term')
            ->fields(['field_membership_term_value' => $term])
            ->condition('entity_id', $contribution->id)
            ->execute();
        } 
        else {
          // Membership_term does not exist, insert.
          $fields = array(
            'bundle' => 'civicrm_contribution',
            'deleted' => 0,
            'entity_id' => $contribution->id,
            'revision_id' => $contribution->id,
            'langcode' => 'en',
            'delta' => 0,
            'field_membership_term_value' => $term,
          );

          $this->database->insert('civicrm_contribution__field_membership_term')
            ->fields($fields)
            ->execute();
        } 
      } 
      else {
        $missing_terms[$contribution->id]['id'] = $contribution->id;
        $missing_terms[$contribution->id]['receive_date'] = $pay_date;
        $missing_terms[$contribution->id]['total_amount'] = $pay_amount;
      }  
    }
   
    // Build table
    $title = "<h3 class='w3-block-title'>Membership contributions without an assigned term</h3>";
    $header = ['Contribution ID', 'Date', 'Membership Fee'];
    $help = "Return to help page for next step.";
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#caption' => $help,
      '#rows' => $missing_terms,
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