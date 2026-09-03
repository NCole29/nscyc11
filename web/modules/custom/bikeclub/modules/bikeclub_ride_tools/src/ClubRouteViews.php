<?php

namespace Drupal\bikeclub_ride_tools;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Class implementing EntityViewsDataInterface exposes custom entity to views.
 * Reference this class in ClubRoute.php annotation handlers.
  */

class ClubRouteViews extends EntityViewsData implements EntityViewsDataInterface {
}
