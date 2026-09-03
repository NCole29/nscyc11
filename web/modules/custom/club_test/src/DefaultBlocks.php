<?php

namespace Drupal\club_test;

class DefaultBlocks {

	/**
	 * Output default content.
	 */
	public static function getBlocks($ids) {

		$entityStorage = \Drupal::entityTypeManager()->getStorage('block_content'); 
		$blocks = $entityStorage->loadMultiple($ids);

		echo "<br><h3>Block UUIDs - copy to modules/custom/export_content/export_content.info.yml and enable module</h3>";
		foreach ($blocks as $block) {
			echo '<br>- ' . $block->uuid->value;
		}

		echo "<br><h3>Copy to modules/custom/export_content/renamit.sh</h3>";
		foreach ($blocks as $block) {
			$name = str_replace(' ','_',strtolower($block->info->value)); 
			echo '<br>mv content/block/' . $block->uuid->value . '.yml  content/block/' .  $name . '.yml';
		}

	}
}