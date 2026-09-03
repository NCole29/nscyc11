<?php

namespace Drupal\bikeclub\Plugin\Filter;

use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Filter(
  id: "filter_table",
  title: new TranslatableMarkup("Club Table Filter"),
  description: new TranslatableMarkup("Custom filter to insert css class on table when displayed."),
  type: 3,
  status: FALSE
)]
class TableFilter extends FilterBase {

  public function process($text, $langcode) {

    $replace = '<table align="center" class="w3-table-all"';
    $new_text = str_replace('<table', $replace, $text);

    return new FilterProcessResult($new_text);
  }
}
