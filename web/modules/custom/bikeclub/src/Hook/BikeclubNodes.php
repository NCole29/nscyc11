<?php

declare(strict_types=1);

namespace Drupal\bikeclub\Hook;

use Drupal\bikeclub\Controller\RenameImages;
use Drupal\bikeclub\Controller\RWGPSClient;
use Drupal\bikeclub\Controller\UpdateRecurDates;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;

class BikeclubNodes {

/**
 * Constructor for BikeclubNode Hooks.
 */
 public function __construct(
    protected ConfigFactoryInterface $config,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
    protected RenameImages $renameImages,
    protected RwgpsClient $rwgpsClient
  ) {
  }
  
  /**
   * Implements hook_NODE_presave().
   */
  #[Hook('node_presave')]
  public function nodePresave(EntityInterface $node) {
  
    // CODE PER CONTENT TYPE.
    // Conditional_fields module does not "zero out" entity reference fields so it's done here.
    $node_type = $node->bundle();
  
    switch ($node_type) {
      case 'banner':
        $this->fixImage($node);
      break;

      case 'announcement':
      case 'page':
        $this->fixImage($node);
        $this->clearContact($node); 
      break;

      case 'event':
        $this->fixImage($node);
        $this->clearContact($node);

        $regType = $node->get('field_registration_type')->value;
        if ($regType <> 1) {       // 1 = webform
          unset($node->field_webform);
        } elseif ($regType <> 2) {   // 2 = link
          unset($node->field_registration_link);
        }
        $this->clearWebformFields($node);
      break;

      case 'faqs':
        $this->clearContact($node);
      break;
        
      case 'location':
        // Display message if Geocoder Provider is not configured.
        $googleAPI = $this->config->get('geocoder.geocoder_provider.googlemaps')->get('configuration.apiKey');
        
        if (!$googleAPI) {
          $link = \Drupal\Core\Url::fromRoute('entity.geocoder_provider.collection')->toString();
          $content = 'A Google Maps link will be displayed for this address. But addresses must be geocoded for display on maps on the website.
          Please configure a <u><a href="@link">geocoder provider</a></u>.';
          
          $this->messenger->addWarning(t($content, [ '@link' => $link ]));
        }
      break;

      case 'ride':
        if (!empty($node->field_date->value)) {
          // Fill day-of-week for displaying weekend vs weekday rides.
          $node->field_dayofweek = DateHelper::dayOfWeek($node->field_date->value);

          // Fill schedule date (entity reference field).
          $date_formatter = \Drupal::service('date.formatter');
          $date = $date_formatter->format($node->get('field_date')->date->getTimestamp(), 'custom', 'Y-m-d');

          $query = $this->entityTypeManager->getStorage('club_schedule')->getQuery();
          $id = $query
            ->accessCheck(FALSE)
            ->condition('schedule_date', $date, '=')
            ->execute();
          $id = reset($id);

          if ($id) {
            $node->field_schedule_date->target_id = $id;
          }   
        }  
        $this->updateRideFields($node);  
      break;

      case 'recurring_ride':
        $update_recur_dates = \Drupal::service('bikeclub.update_recur_dates');

        // Get numeric day-of-week from timestamp. 
        // Typecast field_datetime because its a 'bigint' type that Drupal returns as string. 
        $date_time = (int) $node->field_datetime->value;
        $node->field_dayofweek = date('w',$date_time);

        $this->updateRideFields($node);

        // Update recurring_dates.
        if ($node->id()) {
          $update_recur_dates->deleteDates($node->id()); // Delete future dates.
          $update_recur_dates->addDates($node, 0);// 0 = don't add all, just future dates.
        } 
      break;

      // Webform nodes.
      case 'webform': 
        $this->fixImage($node);
        $this->clearContact($node);
        $this->clearWebformFields($node);
      break;
    } 
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node.
   */
  #[Hook('node_insert')]
  function nodeInsert(EntityInterface $node) {
    if ($node->bundle() == 'recurring_ride') {
      $update_recur_dates = \Drupal::service('bikeclub.update_recur_dates');
      $update_recur_dates->addDates($node, 1);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for node.
   */
  #[Hook('node_delete')]
  function nodeDelete(EntityInterface $node) {

    // Delete recurring dates when recurring ride is deleted.
    if ($node->bundle() == 'recurring_ride') {
    
      // Select recurring_dates with field_recurid = recurring_ride node ID. 
      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $ids = $query
        ->condition('field_recurid', $node->id())
        ->accessCheck(FALSE)
        ->execute();

      $node_storage = $this->entityTypeManager->getStorage('node');
      $entities = $node_storage->loadMultiple($ids);

      foreach ($entities as $recur_date) {
        $recur_date->delete();
      }
    }
  }

  /** 
   * Set image name/category.
   * Applies to Announcement, Basic page, Event, Webform node.
   */
  public function fixImage($node) {
        
    // Replace image name with Alt text.
    if ($node->hasField('field_text') & isset($node->field_text)) {
      $this->renameImages->fixMedia($node);
    }
    if ($node->hasField('field_components') & isset($node->field_components->target_id)) {
      $this->renameImages->fixPmedia($node);
    }
    if ($node->hasField('banner_image') & isset($node->banner_image)) {
      $this->renameImages->fixBanners($node);
    }
  }

  /** 
   * Clear personal contact field if contact form not Personal.
   * Applies to all content types except Rides.
   */
  public function clearContact($node) {
    // Clear personal contact form if selection has changed.
    if (isset($node->field_contact_person) && $node->field_contact_form->target_id <> 'personal') {
      unset($node->field_contact_person);
    }    
  }

  /**
   * Update ride fields.
   */
  public function updateRideFields($node) {

    // Set image name & category.  
    if (isset($node->field_ride_picture->target_id)) {
      $this->renameImages->fixRideImage($node->field_ride_picture->target_id);
    }

    // Clear Times if multiple time is unchecked.
    if (isset($node->field_time) && $node->field_multiple_times->value == 0) {
      unset($node->field_time);
    }

    // Clear lunch location if same as ride start.
    if (isset($node->field_lunch_same) & $node->field_lunch_same->value == 0) {
      unset($node->field_lunch_place);
    }

    // Clear the registration fields upon update.
    if (isset($node->field_webform) && $node->field_registration_required->value == 0) {
      unset($node->field_webform);
    }
    $this->clearWebformFields($node);

    // Store routes when node is published (not when draft to keep junk out of route table.)
    if ($node->status->value == 1 && !empty($node->field_rwgps_routes)) {
      $ride_start = $node->field_location->target_id;

      foreach ($node->field_rwgps_routes as $routeId) {
        if (!empty($routeId->target_id)){
          if (preg_match('/[^0-9()]/', $routeId->target_id)) {
            \Drupal::messenger()->addStatus('A RWGPS route number has non-numeric characters and was not saved');
            unset($routeId->target_id);
          } 
          if (is_numeric($routeId->target_id)) {
            $this->rwgpsClient->getRouteInfo($routeId->target_id, $ride_start);
          }
        }
      }
    }  
  }

  /**
   * Clear webform fields.
   */
  public function clearWebformFields($node) {
    
    if (empty($node->field_webform->target_id)) {
      unset($node->field_webform_limit);
      unset($node->field_webform_closing);
    }
    elseif ($node->field_webform->status <> 'scheduled'){
      unset($node->field_webform_closing);
    }
    elseif ($node->field_webform->status == 'scheduled') { 
      $node->field_webform_closing = $this->convertWebformDate($node->field_webform->close,);
    }
  }
  
  /**
   * Convert webform date stored in site's default timezone to Drupal date (UTC). 
   */ 
  public function convertWebformDate($webformDate) {
    $timezone = $this->config->get('system.date')->get('timezone.default');
    $datetime = new DrupalDateTime($webformDate, $timezone);
    $datetime->setTimezone(new \DateTimeZone('UTC'));

    return $datetime->format('Y-m-d\TH:i:s');
  }
}