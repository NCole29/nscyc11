<?php
use CRM_CiviDiscount_ExtensionUtil as E;

// This generates 1 SavedSearch with 3 slightly different displays.
// It conditionally includes Event & Membership fields if enabled.
$savedSearch = [
  'name' => 'SavedSearch_Discount_Uses',
  'entity' => 'SavedSearch',
  'cleanup' => 'always',
  'update' => 'unmodified',
  'params' => [
    'version' => 4,
    'values' => [
      'name' => 'Discount_Uses',
      'label' => E::ts('Discount Uses'),
      'form_values' => NULL,
      'mapping_id' => NULL,
      'search_custom_id' => NULL,
      'api_entity' => 'DiscountTrack',
      'api_params' => [
        'version' => 4,
        'select' => [
          'contact_id.display_name',
          'used_date',
          'item_id',
          'DiscountTrack_DiscountCode_item_id_01.code',
          'DiscountTrack_DiscountCode_item_id_01.organization_id',
        ],
        'orderBy' => [],
        'where' => [],
        'groupBy' => [],
        'join' => [
          [
            'DiscountCode AS DiscountTrack_DiscountCode_item_id_01',
            'LEFT',
            ['item_id', '=', 'DiscountTrack_DiscountCode_item_id_01.id'],
          ],
        ],
        'having' => [],
      ],
      'expires_date' => NULL,
      'description' => NULL,
    ],
    'match' => [
      'name',
    ],
  ],
];

$columns = [
  [
    'type' => 'field',
    'key' => 'DiscountTrack_DiscountCode_item_id_01.code',
    'dataType' => 'String',
    'label' => E::ts('Code'),
    'sortable' => TRUE,
  ],
  [
    'type' => 'field',
    'key' => 'contact_id.display_name',
    'dataType' => 'String',
    'label' => E::ts('Used By'),
    'sortable' => TRUE,
    'link' => [
      'entity' => 'Contact',
      'action' => 'view',
      'join' => 'contact_id',
      'target' => '',
    ],
  ],
  [
    'type' => 'field',
    'key' => 'used_date',
    'dataType' => 'Timestamp',
    'label' => E::ts('Date Used'),
    'sortable' => TRUE,
  ],
];

// Add columns for CiviEvent and CiviMember only if they are enabled
$entity = \Civi\Api4\Entity::get(FALSE)
  ->addWhere('name', 'IN', ['Event', 'Membership'])
  ->execute()->indexBy('name');
if (isset($entity['Event'])) {
  $savedSearch['params']['values']['api_params']['select'][] = 'DiscountTrack_Membership_entity_id_01.membership_type_id:label';
  $savedSearch['params']['values']['api_params']['join'][] = [
    'Participant AS DiscountTrack_Participant_entity_id_01',
    'LEFT',
    ['entity_id', '=', 'DiscountTrack_Participant_entity_id_01.id'],
    ['entity_table', '=', "'civicrm_participant'"],
  ];
  $columns[] = [
    'type' => 'field',
    'key' => 'DiscountTrack_Participant_entity_id_01.event_id.title',
    'dataType' => 'String',
    'label' => E::ts('Event'),
    'sortable' => TRUE,
  ];
}
if (isset($entity['Membership'])) {
  $savedSearch['params']['values']['api_params']['select'][] = 'DiscountTrack_Participant_entity_id_01.event_id.title';
  $savedSearch['params']['values']['api_params']['join'][] = [
    'Membership AS DiscountTrack_Membership_entity_id_01',
    'LEFT',
    ['entity_id', '=', 'DiscountTrack_Membership_entity_id_01.id'],
    ['entity_table', '=', "'civicrm_membership'"],
  ];
  $columns[] = [
    'type' => 'field',
    'key' => 'DiscountTrack_Membership_entity_id_01.membership_type_id:label',
    'dataType' => 'Integer',
    'label' => E::ts('Membership'),
    'sortable' => TRUE,
  ];
}

$searches = [$savedSearch];

// Make 3 slightly different search displays - the only difference is the name, label & a few columns
$displays = [
  'Discount_Uses_Report' => [
    'label' => E::ts('Discount Uses Report'),
    'without' => ['DiscountTrack_DiscountCode_item_id_01.code'],
  ],
  'Discount_Uses_Redeemed' => [
    'label' => E::ts('Discount Uses Redeemded'),
    'without' => ['contact_id.display_name'],
  ],
  'Discount_Uses_Assigned' => [
    'label' => E::ts('Discount Uses Assigned'),
    'without' => [],
  ],
];

$columns = array_column($columns, NULL, 'key');
foreach ($displays as $name => $display) {
  $searches[] = [
    'name' => "SavedSearch_Discount_Uses_SearchDisplay_$name",
    'entity' => 'SearchDisplay',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => $name,
        'label' => $display['label'],
        'saved_search_id.name' => 'Discount_Uses',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [],
          'limit' => 50,
          'pager' => [
            'show_count' => TRUE,
            'expose_limit' => TRUE,
          ],
          'placeholder' => 5,
          'columns' => array_values(array_diff_key($columns, array_flip($display['without']))),
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
          ],
        ],
        'acl_bypass' => FALSE,
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ];
}

return $searches;
