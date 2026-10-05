<?php

/**
 * Implements hook_views_data().
 */
function club_views_data() {
  $data = [];
  $data['unique_rides']['table']['group'] = t('Ride');
  
  $data['unique_rides']['table']['base'] = [
    'field' => 'nid', // Primary key equivalent 
    'title' => t('Unique rides'),
    'help' => t('Exposes data from the custom MySQL view.'),
  ];

  $data['unique_rides']['nid'] = [
    'title' => t('Node ID'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['type'] = [
    'title' => t('Content type'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['status'] = [
    'title' => t('Status'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['title'] = [
    'title' => t('Ride name'),
    'field' => [
      'id' => 'standard', 
    ],
    'filter' => [
      'id' => 'string',
    ],
  ];
  $data['unique_rides']['created'] = [
    'title' => t('Created date'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['field_date_value'] = [
    'title' => t('Ride date'),
    'field' => [
      'id' => 'standard', 
    ],
        'sort' => [
      'id' => 'datetime', 
    ],
    'filter' => [
      'id' => 'datetime', 
    ],
    'argument' => [
      'id' => 'date', 
    ],
  ];
  $data['unique_rides']['location_id'] = [
    'title' => t('Location ID'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['activity_id'] = [
    'title' => t('Activity ID'),
    'field' => [
      'id' => 'standard', 
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ], 
  ];
  $data['unique_rides']['place'] = [
    'title' => t('Place name'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['street'] = [
    'title' => t('Street'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['city'] = [
    'title' => t('City'),
    'field' => [
      'id' => 'standard', 
    ],
    'filter' => [
      'id' => 'string',
    ],
  ];
  $data['unique_rides']['state'] = [
    'title' => t('State'),
    'field' => [
      'id' => 'standard', 
    ],
  ];
  $data['unique_rides']['rn'] = [
    'title' => t('Record #'),
    'field' => [
      'id' => 'standard', 
    ],
  ];

  return $data;
}
