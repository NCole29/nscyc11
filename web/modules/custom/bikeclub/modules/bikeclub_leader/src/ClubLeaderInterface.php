<?php

namespace Drupal\bikeclub_leader;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a ClubLeader entity.
 *
 * We have this interface so we can join the other interfaces it extends.
 *
 * @ingroup club
 */
interface ClubLeaderInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, EntityPublishedInterface {

}
