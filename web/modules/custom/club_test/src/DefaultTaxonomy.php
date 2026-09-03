<?php

namespace Drupal\club_test;

class DefaultTaxonomy {

	/**
	 * Output default content.
	 */
	public static function getTaxonomy($vocabulary) {

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => $vocabulary]);

    echo "<br><h3>$vocabulary taxonomy UUIDs - copy to modules/custom/export_content/export_content.info.yml and enable module</h3>";
    foreach ($terms as $term) {
      echo '<br>- ' . $term->uuid->value;
    }
    echo "<br><h3>Copy to modules/custom/export_content/renamit.sh</h3>";
    foreach ($terms as $term) {
      $name = str_replace(' ','_',strtolower($term->name->value)); 
      echo '<br>mv content/taxonomy_term/' . $term->uuid->value . '.yml  content/taxonomy_term/' .  $name . '.yml';
    }
  }
}