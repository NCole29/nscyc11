<?php

namespace Drupal\club\Plugin\WebformHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @WebformHandler(
 *   id = "AddUserEmail",
 *   label = @Translation("Add User Email"),
 *   category = @Translation("Club"),
 *   description = @Translation("Use UID submitted in form to lookup user email address for contact form."),
 * )
 */
class AddUserEmail extends WebformHandlerBase {

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
    $uid = $data['recipient_userid'];
    
    // Return if uid is empty.
    if ( empty($uid) ) {
      return;
    }

    // Get user email for form recipient and fill the recipient_email field on submission.
    $email = $this->entityTypeManager
      ->getStorage('user')
      ->load($uid)
      ->getEmail();
   
    if ($email) {
      $webform_submission->setElementData('recipient_email', $email);
    }
  }
}
