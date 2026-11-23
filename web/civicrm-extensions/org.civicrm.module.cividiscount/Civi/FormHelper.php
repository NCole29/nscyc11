<?php

namespace Civi;

use Civi\Api4\Participant;

class FormHelper {

  /**
   * @var \CRM_Core_Form
   */
  private $form;

  private $supportedForms = [
    'CRM_Contribute_Form_Contribution_Main',
    'CRM_Contribute_Form_Contribution_ThankYou',
    'CRM_Contribute_Form_Contribution_Confirm',
    'CRM_Contribute_Form_Contribution',
    'CRM_Event_Form_Participant',
    'CRM_Event_Form_Registration_Register',
    'CRM_Event_Form_Registration_ThankYou',
    'CRM_Event_Form_Registration_Confirm',
    'CRM_Event_Form_Registration_AdditionalParticipant',
    'CRM_Event_Form_ParticipantFeeSelection',
    'CRM_Member_Form_Membership',
    'CRM_Member_BAO_Membership',
    'CRM_Member_Form_MembershipRenewal',
  ];

  /**
   * Constructor.
   *
   * @param \CRM_Contribute_Form_Contribution_Main|\CRM_Contribute_Form_Contribution $form
   */
  public function __construct(\CRM_Core_Form $form) {
    $this->form = $form;
    if (!in_array(get_class($form), $this->supportedForms, TRUE)) {
      \CRM_Core_Error::deprecatedWarning('unexpected form - CiviDiscount may fail to work' . get_class($form));
    }
  }

  public function getEventID(): ?int {
    return method_exists($this->form, 'getEventID') ? $this->form->getEventID() : NULL;
  }

  public function getContributionID(): ?int {
    if (is_callable([$this->form, 'getContributionID'])) {
      return $this->form->getContributionID();
    }
    $params = $this->form->getVar('_params');
    return $params['contributionID'];
  }

  public function getPriceSetID(): ?int {
    if (method_exists($this->form, 'getPriceSetID') && is_callable([$this->form, 'getPriceSetID'])) {
      return $this->form->getPriceSetID();
    }
    \CRM_Core_Error::deprecatedWarning('unable to use supported method to get Price Set ID');
  }

  public function getPriceFieldMetadata(): array {
    if (method_exists($this->form, 'getPriceFieldMetadata')) {
      return $this->form->getPriceFieldMetadata();
    }
    \CRM_Core_Error::deprecatedWarning('unable to use supported method to get Price Set Metadata');
  }

  /**
   * Get the IDs of all price field options on the form.
   *
   * @return array
   */
  public function getPriceFieldOptionIDs(): array {
    $options = [];
    foreach ($this->getPriceFieldMetadata() as $field) {
      $fieldOptions = $field['options'] ?? [];
      foreach ($fieldOptions as $option) {
        $options[$option['id']] = $option;
      }
    }
    return array_keys($options);
  }

  public function getParticipantID(): ?int {
    return method_exists($this->form, 'getParticipantID') ? $this->form->getParticipantID() : NULL;
  }

  /**
   * @param string $value
   *
   * @return int|null|array
   * @throws \CRM_Core_Exception
   */
  public function getParticipantValue(string $value) {
    if (method_exists($this->form, 'getParticipantValue')) {
      return $this->form->getParticipantValue($value);
    }
    $participant = Participant::get(FALSE)->addWhere('id', '-', $this->getParticipantID())->execute()->first();
    return $participant[$value];
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function getMembershipTypes(): array {
    $membershipTypes = [];
    foreach ($this->form->getLineItems() as $lineItem) {
      if (!empty($lineItem['membership_type_id'])) {
        $membershipTypes[] = $lineItem['membership_type_id'];
      }
    }
    return $membershipTypes;
  }

  /**
   *
   * @todo - fix upstream to add these into the line item array, when
   *   returnable.
   *
   * @throws \CRM_Core_Exception
   */
  public function getMembershipIDs(): array {
    if (!$this->form instanceof \CRM_Contribute_Form_Contribution_Confirm) {
      throw new \CRM_Core_Exception('unsupported form');
    }
    return $this->form->_params['createdMembershipIDs'] ?? [];
  }

}
