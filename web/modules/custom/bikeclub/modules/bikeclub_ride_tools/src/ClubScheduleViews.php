<?php

namespace Drupal\bikeclub_ride_tools;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Class implementing EntityViewsDataInterface exposes custom entity to views.
 * Reference this class in Entity php files in annotation handlers.
  */

class ClubScheduleViews extends EntityViewsData implements EntityViewsDataInterface {

  /**
  * {@inheritdoc}
  */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['club_schedule']['schedule_date']['filter'] = [
      'id' => 'datetime',
      'field_name' => 'schedule_date',
    ];
 
    return $data;
  }

}
