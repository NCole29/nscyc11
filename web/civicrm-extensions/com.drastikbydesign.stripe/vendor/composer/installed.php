<?php return array(
    'root' => array(
        'name' => 'civicrm/stripe',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => '9464ce563bb2d9fbd02fa611b4950d8d04337a12',
        'type' => 'civicrm-ext',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'civicrm/stripe' => array(
            'pretty_version' => 'dev-master',
            'version' => 'dev-master',
            'reference' => '9464ce563bb2d9fbd02fa611b4950d8d04337a12',
            'type' => 'civicrm-ext',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'stripe/stripe-php' => array(
            'pretty_version' => 'v16.6.0',
            'version' => '16.6.0.0',
            'reference' => 'd6de0a536f00b5c5c74f36b8f4d0d93b035499ff',
            'type' => 'library',
            'install_path' => __DIR__ . '/../stripe/stripe-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
