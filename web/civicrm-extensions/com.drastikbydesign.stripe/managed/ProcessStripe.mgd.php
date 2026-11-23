<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

return [
  0 =>
    [
      'name' => 'ProcessStripe',
      'entity' => 'Job',
      'update' => 'unmodified',
      'params' =>
        [
          'version' => 3,
          'name' => 'Stripe: Cleanup',
          'description' => 'Cleanup Stripe PaymentIntents',
          'run_frequency' => 'Hourly',
          'api_entity' => 'Stripe',
          'api_action' => 'cleanup',
          'parameters' => '{"version":4,"deleteOld":"-3 month","cancelIncomplete":"-1 hour"}',
        ],
    ],
];
