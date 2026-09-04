<?php

namespace Drupal\club\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Component\Serialization\PhpSerialize;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CiviCRM Payment Status Webform Handler.
 *
 * @WebformHandler(
 *   id = "add_civicrm_payment_status",
 *   label = @Translation("Add CiviCRM Payment Status"),
 *   category = @Translation("Club"),
 *   description = @Translation("Saves CiviCRM contribution status to payment_status field."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class AddPaymentStatus extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    if (!\Drupal::hasService('civicrm')) {
      return;
    }

    // Get the Webform submission ID (sid).
    $sid = $webform_submission->id();

    // Query the webform_civicrm_submissions table.
    $serialized_data  = \Drupal::database()->select('webform_civicrm_submissions', 'wcs')
      ->fields('wcs',['civicrm_data'])
      ->condition('sid', $sid)
      ->execute()
      ->fetchField();

    // Unserialize the data safely.
    $webform_civicrm = $serialized_data ? unserialize($serialized_data, ['allowed_classes' => FALSE]) : [];

    // Extract specific data items.
    $contact_id = $webform_civicrm['contact'][1]['id'] ?? NULL;
    $contrib_id = $webform_civicrm['contribution'][1]['id'] ?? NULL;

    if ($contrib_id) {
      // Query the civicrm_contribution table to get payment status.
      $storage = \Drupal::entityTypeManager()->getStorage("civicrm_contribution");
      $contrib = $storage->load($contrib_id); 
      $status_id = $contrib->get('contribution_status_id')->value;

      $status = match ($status_id) {
        '1' => "Completed",
        '2' => "Pending",
        '3' => "Cancelled",
        '4' => "Failed",
        '5' => "In Progress",
        '6' => "Overdue",
        '7' => "Refunded",
        '8' => "Partially paid",
        '9' => "Pending refund",
        '10' => "Chargeback",
        default => "Unknown",
      };

      // Update payment status in webform submission data.
      $connection = \Drupal::database();
      $connection->update('webform_submission_data')
        ->fields([
          'value' => $status, 
        ])
        ->condition('sid', $sid)
        ->condition('name', 'payment_status')
        ->execute();
    }
  }
}
