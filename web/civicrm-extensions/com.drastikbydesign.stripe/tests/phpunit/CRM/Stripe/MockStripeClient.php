<?php
/**
 * The parent class defines everything via magic get, but when a phpunit mock is created
 * for it php doesn't recognize that for the purposes of errors about dynamic properties.
 * So extend the parent, declare the properties we mock, and then use this class for the
 * mock instead.
 */
class CRM_Stripe_MockStripeClient extends \Stripe\StripeClient {

  public $balanceTransactions;
  public $charges;
  public $checkout;
  public $customers;
  public $events;
  public $invoices;
  public $paymentIntents;
  public $paymentMethods;
  public $plans;
  public $products;
  public $prices;
  public $refunds;
  public $subscriptions;

}
