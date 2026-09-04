<?php

namespace Drupal\club_test;

use Drupal\node\Entity\Node;

class AssignBasedOn {

	public static function addBasedOn() {

		// Master and array of related rides.
		$rides = [
			[71,[303,343,354,574,]],
			[78,[248,352,464,468,]],	
			[89,[209,357,377,]],
			[96,[102,302,431,]],
			[100,[202,306,429,]],
			[109,[331,340,]],
			[234,[332,335,344,348,455,]],
			[237,[300,410,]],
			[246,[333,356,]],
			[299,[392,400,]],
			[314,[422,435,443,461,]],
			[79,[75,]],
			[82,[345,]],
			[84,[347,]],
			[87,[341,]],
			[91,[466,]],
			[126,[127,]],
			[128,[129,]],
			[130,[444,]],
			[131,[208,]],
			[136,[428,]],
			[139,[99,]],
			[144,[191,]],
			[156,[330,]],
			[226,[286,]],
			[244,[465,]],
			[301,[409,]],
			[338,[450,]],
			[342,[472,]],
			[369,[430,]],
			[375,[141,]],
			[375,[192,]],
			[423,[404,]],
			[440,[436,]],
		];

		$db = \Drupal::database();

		foreach($rides as $related) {
			$master = $related[0];
	
			// Update related rides to set field_master = 0 and field_based_on = $master;
			foreach($related[1] as $nid) {
				$node = $db->query("SELECT nid, vid, type, langcode, status, title FROM {node_field_data} WHERE nid = :nidlist", [':nidlist' => $nid,])
				->fetch();

				$db->update('node__field_master')
				->condition('entity_id', $nid, '=')
				->fields(['field_master_value' => 0])
				->execute();

				$db->update('node_revision__field_master')
				->condition('entity_id', $nid, '=')
				->fields(['field_master_value' => 0])
				->execute();

				$fields = array(
					'bundle' => $node->type,
					'deleted' => 0,
					'entity_id' => $nid,
					'revision_id' => $node->vid,
					'langcode' => $node->langcode,
					'delta' => 0,
					'field_based_on_target_id' => $master,
				);
	
				$db->insert('node__field_based_on')
					->fields($fields)
					->execute();
				$db->insert('node_revision__field_based_on')
					->fields($fields)
					->execute();
			} // end loop over related
		} // end loop over rides

	}
}

