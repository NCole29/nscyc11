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

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

define('STRIPE_PHPUNIT_TEST', 1);

/**
 * This class provides helper functions for other Stripe Tests. There are no
 * tests in this class.
 *
 * @group headless
 */
abstract class CRM_Stripe_TestBase extends CiviUnitTestCase implements HeadlessInterface, HookInterface {

  /**
   * @var int*/
  protected int $created_ts;
  /**
   * @var int*/
  protected int $contributionID;
  /**
   * @var int*/
  protected int $financialTypeID;
  /**
   * @var array*/
  protected array $contact;
  /**
   * @var int*/
  protected int $contactID;
  /**
   * @var int*/
  protected int $paymentProcessorID;
  /**
   * @var arrayofpaymentprocessorconfigurationvalues*/
  protected array $paymentProcessor;
  /**
   * @var CRM_Core_Payment_Stripe*/
  protected \CRM_Core_Payment_Stripe $paymentObject;
  /**
   * @var string*/
  protected string $trxn_id;
  /**
   * @var string*/
  protected string $processorID;
  /**
   * @var string*/
  protected string $cc = '4111111111111111';
  /**
   * @var string*/
  protected string $total = '400.00';

  /**
   * @var array*/
  protected array $contributionRecur = [
    'frequency_unit' => 'month',
    'frequency_interval' => 1,
    'installments' => 5,
  ];

  /**
   * List of extensions required for this set of tests
   *
   * @var array|string[]
   */
  protected array $requiredExtensions = [
    'mjwshared' => 'mjwshared',
    'firewall' => 'firewall',
    'contributionlog' => 'contributionlog',
  ];

  /**
   * @inheritDoc
   */
  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    static $reInstallOnce = TRUE;

    $reInstall = FALSE;
    // @todo when is this `if` statement ever true?
    if (!isset($reInstallOnce)) {
      $reInstallOnce = TRUE;
      $reInstall = TRUE;
    }

    $headless = \Civi\Test::headless();

    foreach ($this->requiredExtensions as $extensionKey) {
      if ($this->isExtensionMissing($extensionKey)) {
        civicrm_api3('Extension', 'download', ['key' => $extensionKey]);
      }
      $headless->install($extensionKey);
    }

    return $headless
      ->installMe(__DIR__)
      ->apply($reInstall);
  }

  /**
   * Whether an extension is absent from every extension directory.
   *
   * Asks CiviCRM rather than checking a path next to this one, which does not
   * hold when this extension is symlinked into the extensions directory.
   */
  private function isExtensionMissing(string $key): bool {
    try {
      \CRM_Extension_System::singleton()->getFullContainer()->getPath($key);
      return FALSE;
    }
    catch (\CRM_Extension_Exception_MissingException $e) {
      return TRUE;
    }
  }

  /**
   * @inheritDoc
   */
  public function setUp(): void {
    parent::setUp();

    // setUpHeadless doesn't get called in phpunit 10 because test listeners
    // need to be done differently, so for now just install here until core
    // supports phpunit 10. This should be a no-op in phpunit 9 because it
    // will have already been installed in headless.
    foreach ($this->requiredExtensions as $extensionKey) {
      if ($this->isExtensionMissing($extensionKey)) {
        civicrm_api3('Extension', 'download', ['key' => $extensionKey, 'install' => 0]);
      }
    }
    civicrm_api3('Extension', 'install', ['keys' => $this->requiredExtensions]);
    // There may be some future edge-cases where we also need to uninstall
    // in tearDown to get an accurate test but at the moment this is fine.
    civicrm_api3('Extension', 'install', ['key' => 'com.drastikbydesign.stripe']);

    // Seeded IDs are offset in tests on newer CiviCRM, so don't assume 1.
    $this->financialTypeID = \Civi\Api4\FinancialType::get(FALSE)
      ->addWhere('name', '=', 'Donation')
      ->execute()
      ->single()['id'];

    // Create Stripe Checkout processor
    $this->setOrCreateStripeCheckoutPaymentProcessor();
    // Create Stripe processor
    $this->setOrCreateStripePaymentProcessor();
    $this->createContact();
    $this->created_ts = time();
  }

  /**
   * @inheritDoc
   */
  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    // civicrm_stripe_paymentintent rows are keyed by literal mock ids (eg.
    // 'pi_mock') reused across many tests in this suite - without truncating
    // it, a later test can pick up an earlier test's stale row and resolve
    // to the wrong Contribution.
    $this->quickCleanup(['civicrm_stripe_customers', 'civicrm_paymentprocessor_webhook', 'civicrm_stripe_paymentintent']);
    parent::tearDown();
  }

  /**
   * @param array $map
   *
   * @return \ValueMapOrDie
   */
  protected function returnValueMapOrDie(array $map): ValueMapOrDie {
    return new ValueMapOrDie($map);
  }

  /**
   * Create contact.
   */
  public function createContact() {
    if (!empty($this->contactID)) {
      return;
    }
    $results = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'Jose',
      'last_name' => 'Lopez',
    ]);
    $this->contactID = $results['id'];
    $this->contact = array_pop($results['values']);

    // Now we have to add an email address.
    $email = 'susie@example.org';
    \Civi\Api4\Email::create(FALSE)
      ->setValues([
        'contact_id' => $this->contactID,
        'email' => $email,
        'location_type_id:name' => 'Home',
      ])
      ->execute();
    $this->contact['email'] = $email;
  }

  /**
   * Create a stripe payment processor.
   *
   */
  public function createPaymentProcessor($overrideParams = []) {
    $params = array_merge([
      'name' => 'Stripe',
      'domain_id' => 'current_domain',
      'payment_processor_type_id:name' => 'Stripe',
      'title' => 'Stripe',
      'is_active' => 1,
      'is_default' => 0,
      'is_test' => 1,
      'is_recur' => 1,
      // Placeholders: the tests mock the Stripe client and never call Stripe.
      'user_name' => 'pk_test_mock',
      'password' => 'sk_test_mock',
      'class_name' => 'Payment_Stripe',
      'billing_mode' => 1,
      'payment_instrument_id' => 1,
    ], $overrideParams);

    // First see if it already exists.
    $paymentProcessor = \Civi\Api4\PaymentProcessor::get(FALSE)
      ->addWhere('class_name', '=', $params['class_name'])
      ->addWhere('is_test', '=', $params['is_test'])
      ->execute()
      ->first();
    if (empty($paymentProcessor)) {
      // Nope, create it.
      $paymentProcessor = \Civi\Api4\PaymentProcessor::create(FALSE)
        ->setValues($params)
        ->execute()
        ->first();
    }

    $this->paymentProcessor = $paymentProcessor;
    $this->paymentProcessorID = $paymentProcessor['id'];
    $this->paymentObject = \Civi\Payment\System::singleton()->getById($paymentProcessor['id']);
  }

  public function setOrCreateStripeCheckoutPaymentProcessor() {
    $this->createPaymentProcessor([
      'name' => 'StripeCheckout',
      'payment_processor_type_id:name' => 'StripeCheckout',
      'title' => 'Stripe Checkout',
      'class_name' => 'Payment_StripeCheckout',
    ]);
  }

  public function setOrCreateStripePaymentProcessor() {
    $this->createPaymentProcessor();
  }

  /**
   * When storing DateTime in database we have to convert to local timezone when running tests
   * Used for checking that available_on custom field is set.
   *
   * @param string $dateInUTCTimezone eg. '2023-06-10 20:05:05'
   *
   * @return string
   * @throws \Exception
   */
  public function getDateinCurrentTimezone(string $dateInUTCTimezone) {
    // create a $dt object with the UTC timezone
    $dt = new DateTime($dateInUTCTimezone, new DateTimeZone('UTC'));

    // get the local timezone
    $loc = (new DateTime)->getTimezone();

    // change the timezone of the object without changing its time
    $dt->setTimezone($loc);

    // format the datetime
    return $dt->format('Y-m-d H:i:s');
  }

  protected function getMocksForOneOffPayment() {
    // Create a mock stripe client.
    $stripeClient = $this->createMock('CRM_Stripe_MockStripeClient');
    // Update our CRM_Core_Payment_Stripe object and ensure any others
    // instantiated separately will also use it.
    $this->paymentObject->setMockStripeClient($stripeClient);

    // Mock the payment methods service.
    $mockPaymentMethod = $this->createMock('Stripe\\PaymentMethod');
    $mockPaymentMethod->method('__get')
      ->will($this->returnValueMap([
        ['id', 'pm_mock'],
      ]));
    $stripeClient->paymentMethods = $this->createMock('Stripe\\Service\\PaymentMethodService');
    $stripeClient->paymentMethods
      ->method('create')
      ->willReturn($mockPaymentMethod);
    $stripeClient->paymentMethods
      ->method('retrieve')
      ->with($this->equalTo('pm_mock'))
      ->willReturn($mockPaymentMethod);

    // Mock the Customers service
    $stripeClient->customers = $this->createMock('Stripe\\Service\\CustomerService');
    $stripeClient->customers
      ->method('create')
      ->willReturn(
        \Stripe\Customer::constructFrom(['id' => 'cus_mock', 'object' => 'customer'])
      );
    $stripeClient->customers
      ->method('retrieve')
      ->with($this->equalTo('cus_mock'))
      ->willReturn(
        \Stripe\Customer::constructFrom(['id' => 'cus_mock', 'object' => 'customer'])
      );

    // Need a mock intent with id and status
    $mockCharge = $this->createMock('Stripe\\Charge');
    $mockCharge
      ->method('__get')
      ->will($this->returnValueMap([
        ['id', 'ch_mock'],
        ['captured', TRUE],
        ['status', 'succeeded'],
        ['balance_transaction', 'txn_mock'],
      ]));

    $mockChargesCollection = new \Stripe\Collection();
    $mockChargesCollection->data = [$mockCharge];

    $mockCharge = \Stripe\Charge::constructFrom([
      'id' => 'ch_mock',
      'object' => 'charge',
      'captured' => TRUE,
      'status' => 'succeeded',
      'balance_transaction' => 'txn_mock',
      'amount' => $this->total * 100,
    ]);
    $stripeClient->charges = $this->createMock('Stripe\\Service\\ChargeService');
    $stripeClient->charges
      ->method('retrieve')
      ->with($this->equalTo('ch_mock'))
      ->willReturn($mockCharge);

    $mockPaymentIntent = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock',
      'object' => 'payment_intent',
      'status' => 'succeeded',
      'latest_charge' => 'ch_mock',
    ]);

    $stripeClient->paymentIntents = $this->createMock('Stripe\\Service\\PaymentIntentService');
    $stripeClient->paymentIntents
      ->method('retrieve')
      ->with($this->equalTo('pi_mock'))
      ->willReturn($mockPaymentIntent);

    $mockPaymentIntentWithAmount = \Stripe\PaymentIntent::constructFrom([
      'id' => 'pi_mock',
      'object' => 'payment_intent',
      'status' => 'succeeded',
      'latest_charge' => 'ch_mock',
      'amount' => '40000',
    ]);
    $stripeClient->paymentIntents
      ->method('update')
      ->with($this->equalTo('pi_mock'))
      ->willReturn($mockPaymentIntentWithAmount);

    $stripeClient->balanceTransactions = $this->createMock('Stripe\\Service\\BalanceTransactionService');
    $stripeClient->balanceTransactions
      ->method('retrieve')
      ->with($this->equalTo('txn_mock'))
      ->willReturn(\Stripe\BalanceTransaction::constructFrom([
        'id' => 'txn_mock',
        'fee' => 1190, /* means $11.90 */
        'currency' => 'usd',
        'exchange_rate' => NULL,
        'object' => 'balance_transaction',
    // 2023-06-10 21:05:05
        'available_on'  => '1686427505',
      ]));

    $mockRefund = \Stripe\Refund::constructFrom([
      'amount' => $this->total * 100,
    //xxx
      'charge_id' => 'ch_mock',
      'created' => time(),
      'currency' => 'usd',
      'id' => 're_mock',
      'object' => 'refund',
    ]);
    $stripeClient->refunds = $this->createMock('Stripe\\Service\\RefundService');
    $stripeClient->refunds
      ->method('all')
      ->willReturn(\Stripe\Collection::constructFrom(['object' => 'list', 'data' => [$mockRefund], 'has_more' => FALSE, 'url' => '/v1/refunds']));
  }

  /**
   * Mock the Checkout Session service (checkout->sessions->create/retrieve) on
   * the stripe client already mocked by getMocksForOneOffPayment(). Both
   * create() and retrieve() return the same mock Session, since our tests
   * only need one Checkout Session at a time.
   *
   * @param array $sessionValues Override/add properties on the mock Session
   *
   * @return \Stripe\Checkout\Session
   */
  protected function getMocksForCheckoutSession(array $sessionValues = []): \Stripe\Checkout\Session {
    // constructFrom() is how the Stripe SDK itself builds these from an
    // API response, and gives us a real Stripe\Checkout\Session matching
    // what createCheckoutSession()'s `: Session` return type expects.
    $mockCheckoutSession = \Stripe\Checkout\Session::constructFrom(array_merge([
      'id' => 'cs_mock',
      'object' => 'checkout.session',
      'customer' => 'cus_mock',
      'payment_intent' => 'pi_mock',
      'status' => 'open',
      'payment_status' => 'unpaid',
      'client_secret' => 'cs_mock_secret',
      'url' => 'https://checkout.stripe.com/c/pay/cs_mock',
    ], $sessionValues));

    $stripeClient = $this->paymentObject->stripeClient;
    $stripeClient->checkout = new stdClass();
    $stripeClient->checkout->sessions = $this->createMock('Stripe\\Service\\Checkout\\SessionService');
    $stripeClient->checkout->sessions
      ->method('create')
      ->willReturn($mockCheckoutSession);
    $stripeClient->checkout->sessions
      ->method('retrieve')
      ->willReturn($mockCheckoutSession);

    return $mockCheckoutSession;
  }

  /**
   * DRY code. Sets up the database as it would be after a recurring contrib
   * has been set up with Stripe.
   *
   * Results in a pending ContributionRecur and a pending Contribution record.
   *
   * The following mock Stripe IDs strings are used:
   *
   * - pm_mock   PaymentMethod
   * - pi_mock   PaymentIntent
   * - cus_mock  Customer
   * - ch_mock   Charge
   * - txn_mock  Balance transaction
   * - sub_mock  Subscription
   *
   * @return array The result from doPayment()
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  protected function mockOneOffPaymentSetup(): array {
    $this->getMocksForOneOffPayment();

    $this->setupPendingContribution();
    // Submit the payment.
    $payment_extra_params = [
      'contributionID'      => $this->contributionID,
      'paymentIntentID'     => 'pi_mock',
    ];

    // Simulate payment
    $this->assertInstanceOf('CRM_Core_Payment_Stripe', $this->paymentObject);
    $doPaymentResult = $this->doPaymentStripe($payment_extra_params);

    //
    // Check the Contribution
    // ...should be pending
    // ...its transaction ID should be our Charge ID.
    //
    $this->checkContrib([
      'contribution_status_id' => 'Pending',
      'trxn_id'                => 'ch_mock',
    ]);

    return $doPaymentResult;
  }

  /**
   * Submit to stripe
   *
   * @param array $params
   *
   * @return array The result from PaymentProcessor->doPayment
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function doPaymentStripe(array $params = []): array {
    // Send in credit card to get payment method. xxx mock here
    $paymentMethod = $this->paymentObject->stripeClient->paymentMethods->create([
      'type' => 'card',
      'card' => [
        'number' => $this->cc,
        'exp_month' => 12,
        'exp_year' => date('Y') + 1,
        'cvc' => '123',
      ],
    ]);

    $paymentIntentID = NULL;
    $paymentMethodID = NULL;

    $firewall = new \Civi\Firewall\Firewall();
    if (!isset($params['is_recur'])) {
      // Send in payment method to get payment intent.
      $paymentIntentParams = [
        'payment_method_id' => $paymentMethod->id,
        'amount' => $this->total,
        'payment_processor_id' => $this->paymentProcessorID,
        'payment_intent_id' => $params['paymentIntentID'] ?? NULL,
        'description' => NULL,
        'csrfToken' => $firewall->generateCSRFToken(),
      ];
      $result = \Civi\Api4\StripePaymentintent::processPublic(TRUE)
        ->setPaymentMethodID($paymentMethod->id)
        ->setAmount($this->total)
        ->setPaymentProcessorID($this->paymentProcessorID)
        ->setIntentID($params['paymentIntentID'] ?? NULL)
        ->setDescription(NULL)
        ->setCsrfToken($firewall->generateCSRFToken())
        ->execute();
      // $result = civicrm_api3('StripePaymentintent', 'process', $paymentIntentParams);

      if (empty($result['success'])) {
        throw new CRM_Core_Exception('StripePaymentintent::processPublic did not return success');
      }
      $paymentIntentID = $result['paymentIntent']['id'];
    }
    else {
      $paymentMethodID = $paymentMethod->id;
    }

    $params = array_merge([
      'payment_processor_id' => $this->paymentProcessorID,
      'amount' => $this->total,
      'paymentIntentID' => $paymentIntentID,
      'paymentMethodID' => $paymentMethodID,
      'email' => $this->contact['email'],
      'contactID' => $this->contact['id'],
      'description' => 'Test from Stripe Test Code',
      'currencyID' => 'USD',
      // Avoid missing key php errors by adding these un-needed parameters.
      'qfKey' => NULL,
      'entryURL' => 'http://civicrm.localhost/civicrm/test?foo',
      'query' => NULL,
      'additional_participants' => [],
    ], $params);

    $ret = $this->paymentObject->doPayment($params);

    /*if ($ret['payment_status'] === 'Completed') {
    civicrm_api3('Payment', 'create', [
    'trxn_id' => $ret['trxn_id'],
    'total_amount' => $params['amount'],
    'fee_amount' => $ret['fee_amount'],
    'order_reference' => $ret['order_reference'],
    'contribution_id' => $params['contributionID'],
    ]);
    }*/
    if (array_key_exists('trxn_id', $ret)) {
      $this->trxn_id = $ret['trxn_id'];
      $contribution = new CRM_Contribute_BAO_Contribution();
      $contribution->id = $params['contributionID'];
      $contribution->trxn_id = $ret['trxn_id'];
      $contribution->save();
    }
    if (array_key_exists('contributionRecurID', $ret)) {
      // Get processor id.
      $sql = "SELECT processor_id FROM civicrm_contribution_recur WHERE id = %0";
      $params = [0 => [$ret['contributionRecurID'], 'Integer']];
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      if ($dao->N > 0) {
        $dao->fetch();
        $this->processorID = $dao->processor_id;
      }
    }
    return $ret;
  }

  /**
   * Confirm that transaction id is legit and went through.
   *
   */
  public function assertValidTrxn() {
    $this->assertNotEmpty($this->trxn_id, "A trxn id was assigned");

    $processor = \Civi\Payment\System::singleton()->getById($this->paymentProcessorID);

    try {
      $processor->stripeClient->charges->retrieve($this->trxn_id);
      $found = TRUE;
    }
    catch (Exception $e) {
      $found = FALSE;
    }

    $this->assertTrue($found, 'Assigned trxn_id is valid.');
  }

  /**
   * Create contribition
   *
   * @param array $params
   *
   * @return array The created contribution
   * @throws \CRM_Core_Exception
   */
  public function setupPendingContribution($params = []): array {
    $contribution = civicrm_api3('contribution', 'create', array_merge([
      'contact_id' => $this->contactID,
      'payment_processor_id' => $this->paymentProcessorID,
      // processor provided ID - use contact ID as proxy.
      'processor_id' => $this->contactID,
      'total_amount' => $this->total,
      'financial_type_id' => $this->financialTypeID,
      'contribution_status_id' => 'Pending',
      'is_test' => 1,
    ], $params));
    $this->assertEquals(0, $contribution['is_error']);
    $contribution = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $contribution['id'])
      ->execute()
      ->first();
    $this->contributionID = $contribution['id'];
    return $contribution;
  }

  /**
   * Sugar for checking things on the contribution.
   *
   * @param array $expectations key => value pairs.
   * @param mixed $contribution
   *   - if null, use this->contributionID
   *   - if array, assume it's the result of a contribution.getsingle
   *   - if int, load that contrib.
   */
  protected function checkContrib(array $expectations, $contribution = NULL) {
    if (!empty($expectations['contribution_status_id'])) {
      $expectations['contribution_status_id'] = CRM_Core_PseudoConstant::getKey(
        'CRM_Contribute_BAO_Contribution', 'contribution_status_id', $expectations['contribution_status_id']);
    }

    if (!is_array($contribution)) {
      $contributionID = $contribution ?? $this->contributionID;
      $this->assertGreaterThan(0, $contributionID);
      $contribution = \Civi\Api4\Contribution::get(FALSE)
        ->addWhere('id', '=', $contributionID)
        ->execute()
        ->first();
    }

    foreach ($expectations as $field => $expect) {
      $this->assertArrayHasKey($field, $contribution);
      $this->assertEquals($expect, $contribution[$field], "Expected Contribution.$field = " . json_encode($expect));
    }
  }

  /**
   * Sugar for checking things on the FinancialTrxn.
   *
   * @param array $expectations key => value pairs.
   * @param int $contributionID
   *   - if null, use this->contributionID
   *   - if array, assume it's the result of a contribution.getsingle
   *   - if int, load that contrib.
   */
  protected function checkFinancialTrxn(array $expectations, int $contributionID) {
    $this->assertGreaterThan(0, $contributionID);
    $latestFinancialTrxn = \Civi\Api4\FinancialTrxn::get(FALSE)
      ->addSelect('*', 'custom.*')
      ->addJoin('Contribution AS contribution', 'LEFT', 'EntityFinancialTrxn')
      ->addWhere('contribution.id', '=', $contributionID)
      ->addWhere('is_payment', '=', TRUE)
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    foreach ($expectations as $field => $expect) {
      $this->assertArrayHasKey($field, $latestFinancialTrxn);
      $this->assertEquals($expect, $latestFinancialTrxn[$field], "Expected FinancialTrxn.$field = " . json_encode($expect));
    }
  }

  /**
   * Sugar for checking things on the contribution recur.
   */
  protected function checkContribRecur(array $expectations) {
    if (!empty($expectations['contribution_status_id'])) {
      $expectations['contribution_status_id'] = CRM_Core_PseudoConstant::getKey(
        'CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', $expectations['contribution_status_id']);
    }
    $this->assertGreaterThan(0, $this->contributionRecurID);
    $contributionRecur = \Civi\Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $this->contributionRecurID)
      ->execute()
      ->first();
    foreach ($expectations as $field => $expect) {
      $this->assertArrayHasKey($field, $contributionRecur);
      $this->assertEquals($expect, $contributionRecur[$field]);
    }
  }

  /**
   * Sugar for checking things on the payment (financial_trxn).
   *
   * @param array $expectations key => value pairs.
   * @param int $contributionID
   *   - if null, use this->contributionID
   *   - Retrieve the payment(s) linked to the contributionID (currently expects one payment only)
   */
  protected function checkPayment(array $expectations, $contributionID = NULL) {
    if (!empty($expectations['contribution_status_id'])) {
      $expectations['contribution_status_id'] = CRM_Core_PseudoConstant::getKey(
        'CRM_Contribute_BAO_Contribution', 'contribution_status_id', $expectations['contribution_status_id']);
    }

    $contributionID = $contributionID ?? $this->contributionID;
    $this->assertGreaterThan(0, $contributionID);
    // We (currently) only support the first payment if there are multiple
    $payment = civicrm_api3('Payment', 'get', ['contribution_id' => $contributionID])['values'];
    $payment = reset($payment);

    foreach ($expectations as $field => $expect) {
      $this->assertArrayHasKey($field, $payment);
      $this->assertEquals($expect, $payment[$field], "Expected Payment.$field = " . json_encode($expect));
    }
  }

}

/**
 * Stubs a method by returning a value from a map.
 */
class ValueMapOrDie implements \PHPUnit\Framework\MockObject\Stub\Stub {

  protected $valueMap;

  public function __construct(array $valueMap) {
    $this->valueMap = $valueMap;
  }

  public function invoke(PHPUnit\Framework\MockObject\Invocation $invocation): mixed {
    // This is functionally identical to phpunit 6's ReturnValueMap
    if (method_exists($invocation, 'parameters')) {
      // phpunit 10
      $params = $invocation->parameters();
    }
    else {
      // phpunit 9
      $params = $invocation->getParameters();
    }
    $parameterCount = \count($params);

    foreach ($this->valueMap as $map) {
      if (!\is_array($map) || $parameterCount !== (\count($map) - 1)) {
        continue;
      }

      $return = \array_pop($map);

      if ($params === $map) {
        return $return;
      }
    }

    // ...until here, where we throw an exception if not found.
    throw new \InvalidArgumentException("Mock called with unexpected arguments: "
      . $invocation->toString());
  }

  public function toString(): string {
    return 'return value from a map or throw InvalidArgumentException';
  }

}
