<?php

namespace Drupal\bikeclub\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\media\Entity\Media;

/**
 * Helper class for setting image name = Alt text and image category = node type.
 */
class RenameImages {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Process images in text and summary fields.
   */
  public function fixMedia($node) {
    $uuids = $this->getUUIDs($node); 
    $uuids = array_unique($uuids); // De-dup the array.

    if (count($uuids) == 0) {
      return;
    }
    
    // Get the taxonomy term id for the node type.
    $image_cat = $this->getImageCatId($node);

    foreach ($uuids as $key => $uuid) {
      $media = $this->entityTypeManager->getStorage('media')->loadByProperties(['uuid' => $uuid]);

      if ($media) {
        $media = reset($media);
        $alt = $media->get('thumbnail')[0]->get('alt')->getString();

        if ($alt) {
          $media->set('name',$alt)->save();
        }
        if ($image_cat) {
          $media->set('field_image_category',$image_cat)->save();
        }
      }
    }
    return;
  }

  /**
   * Process images in paragraph components.
   */
  public function fixPmedia($node) { 
    $paragraphs = $node->get('field_components')->referencedEntities();
    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
  
    foreach ($paragraphs as $key => $entity) {
      $paragraph = $paragraphStorage->load($entity->id());
  
      if ($paragraph->hasField('field_image')) {
        $this->setImageName($paragraph->field_image->target_id);
      }
    }
    return;
  }

  /**
   * Process banner and gallery images. 
   */
  public function fixBanners($node) {
    $banners = $node->get('banner_image')->referencedEntities();

    foreach ($banners as $key => $entity) {
      $this->setImageName($entity->id()); 
    }
    return;
  }

  /**
   * Process ride image (limit 1). 
   */
  public function fixRideImage($mid) {
    $media = Media::load($mid);
    $alt = $media->get('thumbnail')[0]->get('alt')->getString();

    // Get taxonomy term ID.
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term = $termStorage->loadByProperties(['vid' => 'image_category', 'name' => 'Ride']);
    $term = reset($term);
    $image_cat = $term->id();

    if ($alt) {
      $media->set('name',$alt)->save();
    }
    if ($image_cat) {
      $media->set('field_image_category', $image_cat)->save();
    }
  }

  /**
   * Get UUIDs for media embedded in summary and text fields.
   */
  public function getUUIDs($node) {
    $fields = ['field_summary','field_text'];
    $uuids = [];

    foreach ($fields as $field) {
      if ($node->hasField($field) && isset($node->get($field)[0])) {
        $field_value = $node->get($field)[0]->getValue(); // Array with format and value (i.e. text)
        $text = $field_value['value'];

        if (stristr($text, 'data-entity-type="media"') !== FALSE) {
          $dom = Html::load($text);
          $xpath = new \DOMXPath($dom);

          // Fill array with image UUID
          foreach ($xpath->query('//*[@data-entity-type="media" and @data-entity-uuid]') as $image) {
            $uuids[] = $image->getAttribute('data-entity-uuid');
          }
        }
      }
    }
    return $uuids;
  }

  public function getImageCatId($node) {
    $node_type = ucfirst($node->bundle());
    $node_type = str_replace("_"," ",$node_type);

    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    $term = $termStorage->loadByProperties(['vid' => 'image_category', 'name' => $node_type]);

    if(!$term) {
      $term = $termStorage->loadByProperties(['vid' => 'image_category', 'name' => 'Other']);
    }

    $term = reset($term);
    $tid = $term->id();
    return $tid;
  }

  /**
   * Set image name equal to Alt text.
   */
  public function setImageName($image_id) {
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $media = $mediaStorage->load($image_id);

    if (isset($media)) {
      $alt = $media->get('thumbnail')[0]->get('alt')->getString();
      
      if (!empty($alt)) {
        $media->set('name',$alt)->save();
      }
    }
    return;
  }
}
