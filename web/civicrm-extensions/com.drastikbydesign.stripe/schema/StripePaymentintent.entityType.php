<?php
use CRM_Stripe_ExtensionUtil as E;

return [
  'name' => 'StripePaymentintent',
  'table' => 'civicrm_stripe_paymentintent',
  'class' => 'CRM_Stripe_DAO_StripePaymentintent',
  'getInfo' => fn() => [
    'title' => E::ts('Stripe Paymentintent'),
    'title_plural' => E::ts('Stripe Paymentintents'),
    'description' => E::ts('Stripe PaymentIntents'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'UI_stripe_intent_id' => [
      'fields' => [
        'stripe_intent_id' => TRUE,
      ],
      'unique' => TRUE,
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'stripe_intent_id' => [
      'title' => E::ts('Stripe Intent ID'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('The Stripe PaymentIntent/SetupIntent/PaymentMethod ID'),
    ],
    'contribution_id' => [
      'title' => E::ts('Contribution ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK ID from civicrm_contribution'),
      'entity_reference' => [
        'entity' => 'Contribution',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'payment_processor_id' => [
      'title' => E::ts('Payment Processor'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('Foreign key to civicrm_payment_processor.id'),
      'pseudoconstant' => [
        'table' => 'civicrm_payment_processor',
        'key_column' => 'id',
        'label_column' => 'name',
      ],
      'entity_reference' => [
        'entity' => 'PaymentProcessor',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'description' => [
      'title' => E::ts('Description'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Description of this paymentIntent'),
    ],
    'status' => [
      'title' => E::ts('Status'),
      'sql_type' => 'varchar(25)',
      'input_type' => 'Text',
      'description' => E::ts('The status of the paymentIntent'),
    ],
    'identifier' => [
      'title' => E::ts('Identifier'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('An identifier that we can use in CiviCRM to find the paymentIntent if we do not have the ID (eg. session key)'),
    ],
    'contact_id' => [
      'title' => E::ts('Contact ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contact'),
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'sql_type' => 'timestamp',
      'input_type' => NULL,
      'description' => E::ts('When was paymentIntent created'),
      'default' => 'CURRENT_TIMESTAMP',
    ],
    'flags' => [
      'title' => E::ts('Flags'),
      'sql_type' => 'varchar(100)',
      'input_type' => 'Text',
      'description' => E::ts('Flags associated with this PaymentIntent (NC=no contributionID when doPayment called)'),
    ],
    'referrer' => [
      'title' => E::ts('Referrer'),
      'sql_type' => 'varchar(1024)',
      'input_type' => 'Text',
      'description' => E::ts('HTTP referrer of this paymentIntent'),
    ],
    'extra_data' => [
      'title' => E::ts('Extra Data'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Extra data collected to help with diagnostics (such as email, name)'),
    ],
  ],
];
