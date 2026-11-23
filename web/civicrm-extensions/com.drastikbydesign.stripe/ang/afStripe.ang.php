<?php

// Angular module afStripe.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
return [
  'js' => [
    'ang/afStripe.js',
    'ang/afStripe/*.js',
    'ang/afStripe/*/*.js',
  ],
  'partials' => ['ang/afStripe'],
  'settings' => [],
  'requires' => ['afCheckout'],
  'exports' => [
    'af-stripe-embedded-checkout' => 'E',
  ],
];
