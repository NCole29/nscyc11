<?php

namespace Drupal\club_temp\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filter to style tables for d8w3css theme
 *
 * @Filter(
 *   id = "filter_table",
 *   title = @Translation("Club Table Filter"),
 *   description = @Translation("Custom filter to insert css class on table when displayed."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   status = FALSE,
 * )
 */
class TableFilter extends FilterBase {

  public function process($text, $langcode) {

    $replace = '<table align="center" class="w3-table-all"';
    $new_text = str_replace('<table', $replace, $text);

    return new FilterProcessResult($new_text);
  }
}
