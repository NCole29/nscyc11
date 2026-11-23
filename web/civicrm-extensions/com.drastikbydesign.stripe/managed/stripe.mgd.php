<?php

use CRM_Stripe_ExtensionUtil as E;

/**
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
 */
return [
  [
    'name' => 'Stripe',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Stripe',
        'title' => E::ts('Stripe'),
        'description' => E::ts('Stripe Payment Processor'),
        'user_name_label' => 'Publishable key',
        'password_label' => 'Secret Key',
        'signature_label' => 'Webhook Secret',
        'class_name' => 'Payment_Stripe',
        'url_site_default' => 'http://unused.com',
        'url_site_test_default' => 'http://unused.com',
        'billing_mode' => 1,
        'is_recur' => TRUE,
        'payment_instrument_id:name' => 'Credit Card',
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'StripeCheckout',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'StripeCheckout',
        'title' => E::ts('Stripe Checkout'),
        'description' => E::ts('Stripe Checkout Payment Processor'),
        'user_name_label' => 'Publishable key',
        'password_label' => 'Secret Key',
        'signature_label' => 'Webhook Secret',
        'class_name' => 'Payment_StripeCheckout',
        'url_site_default' => 'http://unused.com',
        'url_site_test_default' => 'http://unused.com',
        'billing_mode' => 4,
        'is_recur' => TRUE,
        'payment_instrument_id:name' => 'Credit Card',
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_instrument_OptionValue_Stripe',
    'entity' => 'OptionValue',
    'cleanup' => 'never',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id:name' => 'payment_instrument',
        'label' => E::ts('Stripe'),
        'name' => 'Stripe',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => ['option_group_id', 'name'],
    ],
  ],
];
