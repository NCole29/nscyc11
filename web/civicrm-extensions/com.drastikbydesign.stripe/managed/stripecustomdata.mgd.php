<?php

/**
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
 */

use CRM_Stripe_ExtensionUtil as E;

// Group is added by mjwshared
$customGroup = \Civi\Api4\CustomGroup::get(FALSE)
  ->addWhere('name', '=', 'Payment_details')
  ->execute()
  ->first();
if (empty($customGroup)) {
  return [];
}
else {
  return [
    [
      'name' => 'CustomGroup_Payment_details_CustomField_available_on',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'available_on',
          'label' => E::ts('Available On'),
          'data_type' => 'Date',
          'html_type' => 'Select Date',
          'help_pre' => E::ts('If there is a delay between your payment provider receiving the payment and it arriving in your bank account this is the date that it should be available in your account.'),
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'date_format' => 'yy-mm-dd',
          'time_format' => 2,
          'column_name' => 'available_on',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],
    [
      'name' => 'CustomGroup_Payment_details_CustomField_exchange_rate',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'exchange_rate',
          'label' => E::ts('Exchange Rate'),
          'data_type' => 'Float',
          'html_type' => 'Text',
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'column_name' => 'exchange_rate',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],
    [
      'name' => 'CustomGroup_Payment_details_CustomField_payout_amount',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'payout_amount',
          'label' => E::ts('Payout Amount'),
          'data_type' => 'Float',
          'html_type' => 'Text',
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'column_name' => 'payout_amount',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],
    [
      'name' => 'CustomGroup_Payment_details_CustomField_payout_currency',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'payout_currency',
          'label' => E::ts('Payout Currency'),
          'html_type' => 'Text',
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'column_name' => 'payout_currency',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],

    // Collect the payout fee and net amount, to help with reconciliation of
    // foreign currency payments.
    [
      'name' => 'CustomGroup_Payment_details_CustomField_payout_fee',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'payout_fee',
          'label' => E::ts('Payout Fee'),
          'data_type' => 'Float',
          'html_type' => 'Text',
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'column_name' => 'payout_fee',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],
    [
      'name' => 'CustomGroup_Payment_details_CustomField_charge_amount',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'charge_amount',
          'label' => E::ts('Charge Amount'),
          'data_type' => 'Float',
          'html_type' => 'Text',
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'column_name' => 'charge_amount',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],
    [
      'name' => 'CustomGroup_Payment_details_CustomField_charge_currency',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'charge_currency',
          'label' => E::ts('Charge Currency'),
          'html_type' => 'Text',
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'column_name' => 'charge_currency',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],
    [
      'name' => 'CustomGroup_Payment_details_CustomField_charge_fee',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Payment_details',
          'name' => 'charge_fee',
          'label' => E::ts('Charge Fee'),
          'data_type' => 'Float',
          'html_type' => 'Text',
          'text_length' => 255,
          'is_searchable' => TRUE,
          'is_view' => TRUE,
          'column_name' => 'charge_fee',
        ],
        'match' => [
          'name',
          'custom_group_id',
        ],
      ],
    ],
  ];
}
