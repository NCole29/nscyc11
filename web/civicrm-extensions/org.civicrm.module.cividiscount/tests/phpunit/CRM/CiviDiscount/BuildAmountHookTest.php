<?php

use Civi\Test\Api4TestTrait;
use Civi\Test\FormWrapper;

/**
 * @group headless
 *
 * Covers the regression fixed by !374 (dev/core issue #342): a manually
 * entered discount code must be filtered by entity/price-set context, not
 * matched on the code string alone.
 *
 * Unlike CRM/CiviDiscount/DiscountCalculatorTest.php (which exercises
 * CRM_CiviDiscount_DiscountCalculator directly - logic that was never
 * buggy), these tests drive real CRM_Event_Form_Participant and
 * CRM_Member_Form_Membership instances through preProcess()/buildForm(),
 * which is what actually fires cividiscount_civicrm_buildAmount() and
 * cividiscount_civicrm_membershipTypeValues() - the two call sites this MR
 * changes. Before the fix, both used DiscountCalculator::getDiscountMatchingCode(),
 * which matches a code without checking entity context at all.
 */
class CRM_CiviDiscount_BuildAmountHookTest extends CiviUnitTestCase {

  use Api4TestTrait;

  public function setUp(): void {
    parent::setUp();
    $this->useTransaction(TRUE);
    // Membership form's "from email" dropdown pulls the logged-in user's
    // own addresses otherwise, which is irrelevant to what's under test here
    // and errors on this site's logged-in test contact's email fixture.
    \Civi::settings()->set('allow_mail_from_logged_in_contact', FALSE);
  }

  private function createDiscountCode(string $code, array $values): array {
    return $this->createTestRecord('DiscountCode', $values + [
      'code' => $code,
      'description' => $code,
      'amount' => 10,
      'amount_type' => 1,
      'is_active' => TRUE,
    ]);
  }

  private function createRealMembershipType(): array {
    $org = $this->createTestRecord('Contact', ['contact_type' => 'Organization']);
    return $this->createTestRecord('MembershipType', [
      'member_of_contact_id' => $org['id'],
      'financial_type_id.name' => 'Member Dues',
      'duration_unit' => 'year',
      'duration_interval' => 1,
    ]);
  }

  /**
   * Build the back-office "add participant" form for a paid event - this is
   * the form cividiscount.php itself special-cases as
   * $isBackOfficeParticipantForm, and is where hook_civicrm_buildAmount
   * actually fires for the event side. `hidden_feeblock` must be present in
   * $_POST because CRM_Event_Form_Participant::preProcess() only builds the
   * fee block on the postback that carries the hidden field it rendered on
   * the initial GET - which a directly-constructed test form never had.
   *
   * cividiscount.php also reads the code via CRM_Utils_Request::retrieve(...,
   * 'REQUEST'), which only sees $_REQUEST - not $_POST alone - so the code
   * must travel as a URL parameter (getFormObject()/getTestForm() merge
   * those into $_REQUEST directly), not as a submitted form value.
   */
  private function getParticipantForm(string $discountCode): \Civi\Test\FormWrappers\EventFormParticipant {
    $contactId = $this->individualCreate();
    $this->eventCreatePaid();
    $submittedValues = [
      'contact_id' => $contactId,
      'event_id' => $this->getEventID(),
      'register_date' => date('Ymd'),
      'role_id' => [1],
      'hidden_feeblock' => 1,
    ];
    return $this->getTestForm('CRM_Event_Form_Participant', $submittedValues, ['cid' => $contactId, 'discountcode' => $discountCode])->processForm(FormWrapper::BUILT);
  }

  /**
   * A membership-only code entered on an event registration must be
   * rejected, not silently matched on the code string alone (dev/core#342).
   */
  public function testMembershipOnlyCodeRejectedForEventRegistration(): void {
    $membershipType = $this->createRealMembershipType();
    $this->createDiscountCode('MEMBERSONLY_HOOK', ['memberships' => [$membershipType['id']]]);

    $form = $this->getParticipantForm('MEMBERSONLY_HOOK');

    $this->assertNotEmpty($form->getValueSetOnForm('discountCodeErrorMsg'));
    $this->assertNull($form->getValueSetOnForm('_discountInfo'));
  }

  /**
   * Positive control for the above: a code scoped to the event actually
   * being registered for must still apply, so that test isn't passing
   * merely because the fixture never matches anything.
   */
  public function testEventScopedCodeAppliesForMatchingEventRegistration(): void {
    // getParticipantForm() creates the event first, so getEventID() below
    // (used to scope the discount code) resolves before the form is built.
    $contactId = $this->individualCreate();
    $this->eventCreatePaid();
    $this->createDiscountCode('EVENTMATCH_HOOK', ['events' => [$this->getEventID()]]);
    $form = $this->getTestForm('CRM_Event_Form_Participant', [
      'contact_id' => $contactId,
      'event_id' => $this->getEventID(),
      'register_date' => date('Ymd'),
      'role_id' => [1],
      'hidden_feeblock' => 1,
    ], ['cid' => $contactId, 'discountcode' => 'EVENTMATCH_HOOK'])->processForm(FormWrapper::BUILT);

    $this->assertEmpty($form->getValueSetOnForm('discountCodeErrorMsg'));
    $this->assertNotNull($form->getValueSetOnForm('_discountInfo'));
  }

  /**
   * An event-only code entered on a membership form must be rejected - the
   * reverse direction of dev/core#342.
   */
  public function testEventOnlyCodeRejectedForMembershipForm(): void {
    $this->createLoggedInUser();
    $this->createRealMembershipType();
    $event = $this->eventCreatePaid();
    $this->createDiscountCode('EVENTONLY_HOOK', ['events' => [$event['id']]]);

    $form = $this->getFormObject('CRM_Member_Form_Membership', [], ['discountcode' => 'EVENTONLY_HOOK']);
    $form->_action = CRM_Core_Action::ADD;
    $form->preProcess();
    $form->buildForm();

    $this->assertNotEmpty($form->get('discountCodeErrorMsg'));
    $this->assertNull($form->get('_discountInfo'));
  }

  /**
   * Positive control for the above: a code scoped to a real membership type
   * must still apply on the membership form.
   */
  public function testMembershipScopedCodeAppliesForMembershipForm(): void {
    $this->createLoggedInUser();
    $membershipType = $this->createRealMembershipType();
    $this->createDiscountCode('MEMBERMATCH_HOOK', ['memberships' => [$membershipType['id']]]);

    $form = $this->getFormObject('CRM_Member_Form_Membership', [], ['discountcode' => 'MEMBERMATCH_HOOK']);
    $form->_action = CRM_Core_Action::ADD;
    $form->preProcess();
    $form->buildForm();

    $this->assertEmpty($form->get('discountCodeErrorMsg'));
    $this->assertNotNull($form->get('_discountInfo'));
  }

}
