<?php

namespace Drupal\bikeclub_ride_tools\Plugin\WebformHandler;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform submission handler.
 *
 * @WebformHandler(
 *   id = "nonmember_submit",
 *   label = @Translation("Nonmember submission handler"),
 *   category = @Translation("Bikeclub"),
 *   description = @Translation("Alters webform submission data."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class NonmemberSubmitHandler extends WebformHandlerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

   /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->messenger = $container->get('messenger');
    $instance->connection = $container->get('database');
    $instance->moduleHandler  = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
   
    $webform_id = $webform_submission->getWebform()->id();

    if (!$webform_id == 'nonmembers') {
      return;
    }

    // Are CiviCRM and CiviCRM_entity installed?
    $civi_installed = $this->moduleHandler->moduleExists('civicrm_entity'); 
    if ($civi_installed) {
     $tag_id = $this->getTagId('Nonmember rider');
    }

    // Get the submission data.
    $data = $webform_submission->getData();

    $ride = $data['ride'];
    $ride_date = $data['ride_date'];
    $nonmembers = $data['nonmember']; // This is the custom composite element with array of riders.    

    // Fill array to get one record per rider with ride and ride_date.
    foreach ($nonmembers as $nonmember) {

      if ($civi_installed) {
        // Get contact_id and tag if not already tagged.
        $contact_id = $this->getContactID($nonmember, $tag_id);

        // Create contact and tag.
        if (!$contact_id) {
         $contact_id = $this->create_contact($nonmember, $tag_id);
        }

        $newdata[] = [
          'ride' => $data['ride'],
          'ride_date' => $data['ride_date'],
          'name' => $nonmember['first_name'] . ' ' . $nonmember['last_name'],
          'email' => $nonmember['email'],
          'civicrm_contact' => $contact_id,
        ];
      }
      else {
        $newdata[] = [
          'ride' => $data['ride'],
          'ride_date' => $data['ride_date'],
          'name' => $nonmember['first_name'] . ' ' . $nonmember['last_name'],
          'email' => $nonmember['email'],
        ];
      }
    }

    $i = 0;
    foreach ($newdata as $new) {

      if ($i == 0) {
        $webform_submission->setData($new);
      } 
      else {
        $values = [
          'webform_id' => $webform_id,
          'data' => $new,
        ];
        /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
        $webform_submission = WebformSubmission::create($values);
        $webform_submission->save();
      }
      $i++;
    }
  }

  /**
   * Get CiviCRM Tag ID.
   *
   * @param string $tag_name
   *   The CiviCRM Contact ID for the name entered in the registration form.
   */
  function getTagId($tag_name) {
    $query = $this->entityTypeManager->getStorage('civicrm_tag')->getQuery();
    $tag_id = $query
      ->condition('name', $tag_name)
      ->execute();
    $tag_id = reset($tag_id);
    return $tag_id;
  }

  /**
   * Assign tag to Contact.
   * 
   * @param int $contact_id
   *   The CiviCRM Contact ID for the name entered in the registration form.
   * @param int $tag_id
   *   The CiviCRM Tag ID for 'Nonmember rider'.
   */
  function assign_tag ($contact_id, $tag_id) {
    // Define the data for the tag entity.
    $tag_data = [
      'entity_table' => 'civicrm_contact',
      'entity_id' => $contact_id,
      'tag_id' => $tag_id,
    ];

    // Create the civicrm_entity_tag (add tag for contact).
    $entity_tag = $this->entityTypeManager->getStorage('civicrm_entity_tag')
      ->create($tag_data);
    $entity_tag->save();
  }

  /**
   * Get CiviCRM Contact Id.
   *
   * @param string $email
   *   The Email entered in the form.
   */
  function getContactId($nonmember, $tag_id) {

    // Use email to lookup contact_id. 
    $query = $this->connection->select('civicrm_email', 't')
      ->fields('t', ['contact_id'])
      ->condition('t.email', $nonmember['email']);
    $contact_id = $query->execute()->fetchField();

     if ($contact_id and $tag_id) {
      // Check if contact is already tagged?
      $query = $this->entityTypeManager->getStorage('civicrm_entity_tag')->getQuery();
      $hasTag = $query
        ->condition('tag_id', $tag_id)
        ->condition('entity_table', 'civicrm_contact')
        ->condition('entity_id', $contact_id)
        ->execute();   
      $hasTag = reset($hasTag);

      if (!$hasTag) {
        $this->assign_tag($contact_id, $tag_id);
      }
    }
    return $contact_id;
  }

  /**
   * Create a CiviCRM Contact.
   * 
   * @param array $nonmember
   *   Data entered in Webform.
   * @param int $tag_id
   *   The CiviCRM Tag ID for 'Nonmember rider'.
   */
  function create_contact($nonmember,$tag_id) {

    // Create a new CiviCRM Contact entity.
    $contact_data = [
      'contact_type' => 'Individual', 
      'first_name' => $nonmember['first_name'],
      'last_name' => $nonmember['last_name'],
      'source' => 'Nonmember rider',
    ];
    $contact = $this->entityTypeManager->getStorage('civicrm_contact')
      ->create($contact_data);

    // Save the new contact, email, and tag.
    try {
      $contact->save();
      $contact_id = $contact->id();

      $email_data = [
        'contact_id' => $contact_id,
        'email' =>  $nonmember['email'],
        'is_primary' => TRUE,
      ];
      $civicrmEmail = $this->entityTypeManager->getStorage('civicrm_email')
        ->create($email_data);
      $civicrmEmail->save();

      $this->assign_tag($contact_id, $tag_id);

      $message = 'CiviCRM Contact created for ' . $nonmember['first_name'] . ' ' . $nonmember['last_name'];
        $this->messenger->addStatus($message);
      } catch (\Exception $e) {
        $this->messenger->addError('Error creating CiviCRM Contact: ' . $e->getMessage());
      }
    return $contact_id;
  }
}
