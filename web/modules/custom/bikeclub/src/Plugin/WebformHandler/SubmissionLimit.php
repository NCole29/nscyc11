<?php

namespace Drupal\bikeclub\Plugin\WebformHandler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @WebformHandler(
 *   id = "SubmissionLimit",
 *   label = @Translation("Submission limit"),
 *   category = @Translation("Bikeclub"),
 *   description = @Translation("Set limit in node field. Webform status is changed to 'closed' on node."),
 * )
 */
class SubmissionLimit extends WebformHandlerBase {

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
  public function postSave(WebformSubmissionInterface $webform_submission, $update = true) {
 
    $source_entity = $webform_submission->getSourceEntity();

    if ($source_entity->hasField('field_webform_limit')) {
      $limit = $source_entity->get('field_webform_limit')->value;
    }

    if (empty($limit)) {
      return;
    }

    // Get webform ID and node ID.
    $webform_id = $webform_submission->getWebform()->id(); 
    $nid = $source_entity->get('nid')->value;
      
    // Count submissions to this webform node.
    $entity_storage = $this->entityTypeManager->getStorage('webform_submission');

    $query = $entity_storage->getQuery();
    $query->condition('webform_id', $webform_id);
    $query->condition('entity_id', $nid);
    $query->accessCheck(FALSE);
    $query->count();
    $count = $query->execute();
    
    if ($count >= $limit) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid); 
    
      // Close the form.
      $node->field_webform->status = 'closed';
      $node->save();
    }
  }
}
