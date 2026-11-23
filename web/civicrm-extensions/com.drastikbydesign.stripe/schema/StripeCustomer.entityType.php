<?php
use CRM_Stripe_ExtensionUtil as E;

return [
  'name' => 'StripeCustomer',
  'table' => 'civicrm_stripe_customers',
  'class' => 'CRM_Stripe_DAO_StripeCustomer',
  'getInfo' => fn() => [
    'title' => E::ts('Stripe Customer'),
    'title_plural' => E::ts('Stripe Customers'),
    'description' => E::ts('Stripe Customers'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'customer_id' => [
      'fields' => [
        'customer_id' => TRUE,
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
    'customer_id' => [
      'title' => E::ts('Stripe Customer ID'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Stripe Customer ID'),
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
    'processor_id' => [
      'title' => E::ts('Payment Processor ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('ID from civicrm_payment_processor'),
      'pseudoconstant' => [
        'table' => 'civicrm_payment_processor',
        'key_column' => 'id',
        'label_column' => 'name',
      ],
    ],
    'currency' => [
      'title' => E::ts('Currency'),
      'sql_type' => 'varchar(3)',
      'input_type' => 'Text',
      'description' => E::ts('3 character string, value from Stripe customer.'),
      'default' => NULL,
    ],
  ],
];
