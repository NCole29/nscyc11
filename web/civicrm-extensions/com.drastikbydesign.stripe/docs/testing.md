# TESTING

!!! note
    The tests included with the Stripe extension have not been updated for 6.x

### PHPUnit
This extension comes with two PHP Unit tests:

 * Ipn - This unit test ensures that a recurring contribution is properly updated after the event is received from Stripe and that it is properly canceled when cancelled via Stripe.
 * Direct - This unit test ensures that a direct payment to Stripe is properly recorded in the database.

Tests can be run most easily via an installation made through CiviCRM Buildkit (https://github.com/civicrm/civicrm-buildkit) by changing into the extension directory and running:

    phpunit6 tests/phpunit/CRM/Stripe/IpnTest.php
    phpunit6 tests/phpunit/CRM/Stripe/DirectTest.php


### Manual Tests

1. Test webform submission with payment and user-select , single processor.

1. Test online contribution page on Wordpress.
1. Test online contribution page on Joomla.
1. Test online event registration (single processor).
1. Test online event registration (no confirmation page).
1. Test online event registration (multiple participants).
1. Test online event registration (multiple processors, Stripe default).
1. Test online event registration (multiple processors, Stripe not default).
1. Test online event registration (cart checkout).

#### Drupal Webform Tests

1. Webform with single payment processor (Stripe) - Amount = 0.
1. Webform with single payment processor (Stripe) - Amount > 0.
1. Webform with multiple payment processor (Stripe selected) - Amount = 0.
1. Webform with multiple payment processor (Stripe selected) - Amount > 0.
1. Webform with multiple payment processor (Pay Later selected) - Amount = 0.
1. Webform with multiple payment processor (Pay Later selected) - Amount > 0.
1. Webform with multiple payment processor (Non-stripe processor selected) - Amount = 0.
1. Webform with multiple payment processor (Non-stripe processor selected) - Amount > 0.
