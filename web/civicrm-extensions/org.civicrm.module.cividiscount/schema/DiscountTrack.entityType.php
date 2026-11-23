<?php
use CRM_CiviDiscount_ExtensionUtil as E;

return [
  'name' => 'DiscountTrack',
  'table' => 'cividiscount_track',
  'class' => 'CRM_CiviDiscount_DAO_Track',
  'getInfo' => fn() => [
    'title' => E::ts('Discount Tracking'),
    'title_plural' => E::ts('Discount Trackings'),
    'description' => E::ts('Record an instance of a discount use'),
    'add' => '1.0',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Discount Item ID'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('ID'),
      ],
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'item_id' => [
      'title' => E::ts('Discount Code ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Item ID of the discount code'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Code'),
      ],
      'entity_reference' => [
        'entity' => 'DiscountCode',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'contact_id' => [
      'title' => E::ts('Contact ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contact ID for the contact that used this discount'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Contact'),
      ],
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'used_date' => [
      'title' => E::ts('Used Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('Date of use'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Date Used'),
      ],
    ],
    'contribution_id' => [
      'title' => E::ts('Contribution ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to contribution table'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Contribution'),
      ],
      'entity_reference' => [
        'entity' => 'Contribution',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'entity_table' => [
      'title' => E::ts('Entity Table'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Select',
      'required' => TRUE,
      'description' => E::ts('Table where referenced item is stored'),
      'add' => '1.0',
      'pseudoconstant' => [
        'callback' => 'CRM_CiviDiscount_BAO_Track::entityTables',
      ],
    ],
    'entity_id' => [
      'title' => E::ts('Entity ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('Foreign key to the referenced item'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Entity'),
      ],
      'entity_reference' => [
        'dynamic_entity' => 'entity_table',
        'key' => 'id',
      ],
    ],
  ],
];
