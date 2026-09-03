<?php

namespace Drupal\bikeclub_ride_tools;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface for club entities.
 *
 * We have this interface so that we can join the other interfaces it extends.
 *
 * @ingroup club
 */
interface ClubInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, EntityPublishedInterface {

}
