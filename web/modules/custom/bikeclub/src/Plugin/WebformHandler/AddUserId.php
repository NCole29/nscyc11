<?php

namespace Drupal\bikeclub\Plugin\WebformHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @WebformHandler(
 *   id = "AddUserId",
 *   label = @Translation("Add User Id"),
 *   category = @Translation("Bikeclub"),
 *   description = @Translation("Use email submitted in form to lookup user id and reset submission owner."),
 * )
 */
class AddUserId extends WebformHandlerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission, $update = true) {
    $data = $webform_submission->getData();
    $email = $data['email'];

    $owner_email = $webform_submission->getOwner()->get('mail')->value;
    
    // Return if email is empty or owner = email on form.
    if ( empty($email) | $owner_email == $email) {
      return;
    }

    // Get user_id for person on form and setOwner on submission.
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $email]);
    $user = $users ? reset($users) : FALSE; // Users is array. Take first entry since its unique.
   
    if ($user) {
      $user_account = $this->entityTypeManager->getStorage('user')->load($user->id());
      $webform_submission->setOwner($user_account);
    }
  }
}
