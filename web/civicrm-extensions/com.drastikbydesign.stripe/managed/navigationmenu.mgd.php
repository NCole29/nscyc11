<?php

use CRM_Stripe_ExtensionUtil as E;

return [
  [
    'name' => 'stripe_settings',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Stripe Settings'),
        'name' => 'stripe_settings',
        'url' => 'civicrm/admin/setting/stripe',
        'permission' => ['administer stripe'],
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviContribute',
        'is_active' => TRUE,
        'has_separator' => 0,
        'weight' => 90,
      ],
      'match' => ['name'],
    ],
  ],
];
