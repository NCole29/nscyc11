<?php
use CRM_CiviDiscount_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Codes Redeemed'),
  'permission' => [
    'administer CiviCRM',
    'administer CiviDiscount',
  ],
  'permission_operator' => 'OR',
  'icon' => 'fa-qrcode',
  'placement' => [
    'contact_summary_tab',
  ],
];
