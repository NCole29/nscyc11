<?php

namespace Drupal\club_ride_tools;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Class implementing EntityViewsDataInterface exposes custom entity to views.
 * Reference this class in Entity php files in annotation handlers.
  */

class ClubScheduleViews extends EntityViewsData implements EntityViewsDataInterface {
}
