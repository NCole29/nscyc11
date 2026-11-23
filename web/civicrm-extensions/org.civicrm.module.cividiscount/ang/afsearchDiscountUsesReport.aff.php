<?php
use CRM_CiviDiscount_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Discount Code Usage'),
  'permission' => [
    'administer CiviCRM',
    'administer CiviDiscount',
  ],
  'permission_operator' => 'OR',
  'icon' => 'fa-qrcode',
  'server_route' => 'civicrm/cividiscount/track',
];
