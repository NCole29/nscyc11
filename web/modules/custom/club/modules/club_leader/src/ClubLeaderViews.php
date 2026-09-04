<?php

namespace Drupal\club_leader;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Class implementing EntityViewsDataInterface exposes custom entity to views.
 * This class is referenced in ClubLeader.php annotation under handlers: 
 *   "views_data" = "Drupal\club_leader\ClubLeaderViews",
 */

class ClubLeaderViews extends EntityViewsData implements EntityViewsDataInterface {
}
