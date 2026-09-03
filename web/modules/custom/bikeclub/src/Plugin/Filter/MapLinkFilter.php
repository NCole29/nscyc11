<?php

namespace Drupal\bikeclub\Plugin\Filter;

use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Filter(
  id: "filter_maplink",
  title: new TranslatableMarkup("Club GoogleMap Filter"),
  description: new TranslatableMarkup("Custom filter to remove 'p' tag on Google Map link in address block."),
  type: 3,
  status: FALSE
)]
class MapLinkFilter extends FilterBase {

  public function process($text, $langcode) {

    $old = '<p><a href="http://www.google.com/maps/place/[node:field_coordinates:latlon]">Google map</a></p>';
    $replace = '<a href="http://www.google.com/maps/place/[node:field_coordinates:latlon]">Google map</a>';
    $new_text = str_replace($old, $replace, $text);

    return new FilterProcessResult($new_text);
  }
}
