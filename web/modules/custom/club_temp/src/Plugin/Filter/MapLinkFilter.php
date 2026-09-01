<?php

namespace Drupal\club_temp\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filter to remove <p> tag on Google Map link
 *
 * @Filter(
 *   id = "filter_maplink",
 *   title = @Translation("Club GoogleMap Filter"),
 *   description = @Translation("Custom filter to remove 'p' tag on Google Map link in address block."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   status = TRUE,
 * )
 */
class MapLinkFilter extends FilterBase {

  public function process($text, $langcode) {

    $old = '<p><a href="http://www.google.com/maps/place/[node:field_coordinates:latlon]">Google map</a></p>';
    $replace = '<a href="http://www.google.com/maps/place/[node:field_coordinates:latlon]">Google map</a>';
    $new_text = str_replace($old, $replace, $text);

    return new FilterProcessResult($new_text);
  }
}
