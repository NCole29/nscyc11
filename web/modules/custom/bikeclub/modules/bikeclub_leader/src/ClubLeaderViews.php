<?php

namespace Drupal\bikeclub_leader;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Class implementing EntityViewsDataInterface exposes custom entity to views.
 * This class is referenced in ClubLeader.php annotation under handlers: 
 *   "views_data" = "Drupal\bikeclub_leader\ClubLeaderViews",
 */

class ClubLeaderViews extends EntityViewsData implements EntityViewsDataInterface {
}
