<?php

namespace Drupal\club;

/**
 * Get Taxonomy Term Id given vocabulary and term names.
 *
 * Method is called by club_forum.module
 *
 */
class GetTermId {

  public static function getTermId($vid, $termName) {
    // Get taxonomy term ID for Unofficial Ad-Hoc Rides. 

    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties([
            'vid' => $vid,
            'name' => [$termName,],
        ]);

    $ids = array_keys($term);
    $termId = reset($ids);    

    return $termId;
  }
}
