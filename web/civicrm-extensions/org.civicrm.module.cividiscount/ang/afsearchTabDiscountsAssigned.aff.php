<?php
use CRM_CiviDiscount_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Codes Assigned'),
  'permission' => [
    'administer CiviCRM',
    'administer CiviDiscount',
  ],
  'permission_operator' => 'OR',
  'placement' => [
    'contact_summary_tab',
  ],
  'summary_contact_type' => [
    'Organization',
  ],
  'icon' => 'fa-qrcode',
];
