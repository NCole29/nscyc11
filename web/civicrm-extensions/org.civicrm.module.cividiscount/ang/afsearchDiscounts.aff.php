<?php
use CRM_CiviDiscount_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Discounts'),
  'icon' => 'fa-qrcode',
  'server_route' => 'civicrm/cividiscount',
  'permission' => [
    'administer CiviCRM',
    'administer CiviDiscount',
  ],
  'permission_operator' => 'OR',
];
