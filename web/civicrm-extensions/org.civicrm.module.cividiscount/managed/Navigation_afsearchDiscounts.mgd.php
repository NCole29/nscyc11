<?php
use CRM_CiviDiscount_ExtensionUtil as E;

return [
  [
    'name' => 'Navigation_afsearchDiscounts',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('CiviDiscount'),
        'name' => 'CiviDiscount',
        'url' => 'civicrm/cividiscount',
        'icon' => 'crm-i fa-qrcode',
        'permission' => [
          'administer CiviCRM',
          'administer CiviDiscount',
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'Administer',
        'weight' => 20,
      ],
      'match' => [
        'domain_id',
        'name',
      ],
    ],
  ],
];
