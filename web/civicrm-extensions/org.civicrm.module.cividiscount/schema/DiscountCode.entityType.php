<?php
use CRM_CiviDiscount_ExtensionUtil as E;

return [
  'name' => 'DiscountCode',
  'table' => 'cividiscount_item',
  'class' => 'CRM_CiviDiscount_DAO_Item',
  'getInfo' => fn() => [
    'title' => E::ts('Discount Code'),
    'title_plural' => E::ts('Discount Codes'),
    'description' => E::ts('A discount item'),
    'add' => '1.0',
    'label_field' => 'code',
  ],
  'getPaths' => fn() => [
    'add' => 'civicrm/cividiscount/discount/add?reset=1',
    'view' => 'civicrm/cividiscount/discount/view?reset=1&id=[id]',
    'update' => 'civicrm/cividiscount/discount/edit?reset=1&id=[id]',
    'delete' => 'civicrm/cividiscount/discount/delete?reset=1&id=[id]',
  ],
  'getIndices' => fn() => [
    // Looked up directly by code in CRM_CiviDiscount_BAO_Item::getValidDiscounts();
    // previously an unindexed full table scan.
    'INDEX_code' => [
      'fields' => [
        'code' => TRUE,
      ],
      'add' => '3.10.7',
    ],
    // Every eligibility query (getValidDiscounts(), validDiscountsByEntity())
    // filters on is_active = 1 first; indexing it lets sites with many
    // inactive/expired discount codes skip scanning them.
    'INDEX_is_active' => [
      'fields' => [
        'is_active' => TRUE,
      ],
      'add' => '3.10.7',
    ],
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
    'code' => [
      'title' => E::ts('Code'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Discount Code'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Code'),
      ],
    ],
    'description' => [
      'title' => E::ts('Description'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Discount Description'),
      'add' => '1.0',
    ],
    'frontend_description' => [
      'title' => ts('Public Description'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'localizable' => TRUE,
      'description' => E::ts('Public description of the discount (will fallback to "description" if empty).'),
      'default' => '',
    ],
    'amount' => [
      'title' => E::ts('Amount'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Amount of discount, actual or percentage'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Amount'),
      ],
    ],
    'amount_type' => [
      'title' => E::ts('Amount Type'),
      'sql_type' => 'varchar(4)',
      'input_type' => 'Select',
      'required' => TRUE,
      'description' => E::ts('Type of discount, actual or percentage'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Type'),
      ],
      'pseudoconstant' => [
        'callback' => 'CRM_CiviDiscount_BAO_Item::amountTypes',
      ],
    ],
    'count_max' => [
      'title' => E::ts('Max Uses'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'required' => TRUE,
      'default' => 0,
      'description' => E::ts('Max number of times this code can be used'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Max Uses (total)'),
      ],
    ],
    'count_user_max' => [
      'title' => E::ts('Max Uses Per User'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'required' => TRUE,
      'default' => 0,
      'description' => E::ts('Max number of times a user can use this code'),
      'add' => '3.9.0',
      'input_attrs' => [
        'label' => E::ts('Max Uses (per user)'),
      ],
    ],
    'count_use' => [
      'title' => E::ts('Count Use'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Number of times this code has been used'),
      'add' => '1.0',
      'default' => 0,
      'input_attrs' => [
        'label' => E::ts('Usage Count'),
      ],
    ],
    'events' => [
      'title' => E::ts('Events'),
      'sql_type' => 'text',
      'input_type' => 'Select',
      'description' => E::ts('Serialized list of events for which this code can be used'),
      'add' => '1.0',
      'serialize' => constant('CRM_Core_DAO::SERIALIZE_SEPARATOR_BOOKEND'),
      'pseudoconstant' => [
        'table' => 'civicrm_event',
        'key_column' => 'id',
        'label_column' => 'title',
        'condition' => 'is_template = 0',
      ],
    ],
    'pricesets' => [
      'title' => E::ts('Pricesets'),
      'sql_type' => 'text',
      'input_type' => 'Select',
      'description' => E::ts('Serialized list of pricesets for which this code can be used'),
      'add' => '1.0',
      'serialize' => constant('CRM_Core_DAO::SERIALIZE_SEPARATOR_BOOKEND'),
      'pseudoconstant' => [
        'table' => 'civicrm_price_field_value',
        'key_column' => 'id',
        'label_column' => 'label',
        'name_column' => 'name',
      ],
    ],
    'memberships' => [
      'title' => E::ts('Memberships'),
      'sql_type' => 'text',
      'input_type' => 'Select',
      'description' => E::ts('Serialized list of memberships for which this code can be used'),
      'add' => '1.0',
      'serialize' => constant('CRM_Core_DAO::SERIALIZE_SEPARATOR_BOOKEND'),
      'pseudoconstant' => [
        'table' => 'civicrm_membership_type',
        'key_column' => 'id',
        'label_column' => 'name',
      ],
    ],
    'autodiscount' => [
      'title' => E::ts('Autodiscount'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'description' => E::ts('Serialized list of entity-based attributes. A user with one of them has the discount automatically applied'),
      'add' => '1.0',
      'serialize' => constant('CRM_Core_DAO::SERIALIZE_JSON'),
    ],
    'organization_id' => [
      'title' => E::ts('Organization ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contact ID for the organization that originated this discount'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Organization'),
      ],
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'active_on' => [
      'title' => E::ts('Active On'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('Date this discount activated'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Start Date'),
      ],
    ],
    'expire_on' => [
      'title' => E::ts('Expire On'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'description' => E::ts('Date this discount expires'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('End Date'),
      ],
    ],
    'is_active' => [
      'title' => E::ts('Enabled'),
      'sql_type' => 'boolean',
      'input_type' => 'Radio',
      'description' => E::ts('Is this discount active?'),
      'add' => '1.0',
      'input_attrs' => [
        'label' => E::ts('Enabled'),
      ],
    ],
    'discount_msg_enabled' => [
      'title' => E::ts('Discount Msg Enabled'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'add' => '2.2',
    ],
    'discount_msg' => [
      'title' => E::ts('Discount Msg'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'input_attrs' => [
        'label' => E::ts('Not Eligible Message'),
        'comment' => 'Message displayed to users not eligible for the discount',
      ],
    ],
    'filters' => [
      'title' => E::ts('Discount Filters'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'description' => E::ts('What filters apply to this discount (json array)'),
      'add' => '2.6',
      'serialize' => constant('CRM_Core_DAO::SERIALIZE_JSON'),
    ],
  ],
];
