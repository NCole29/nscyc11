<?php

/**
 * @file
 * Contains Drupal\bikeclub_ride_tools\Form\RwgpsAPIForm.
 */
namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class RwgpsAPIForm extends ConfigFormBase {
 /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'bikeclub.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rwgps_api_form';
  }

   /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bikeclub.adminsettings');

    $form['rwgps_api'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RWGPS API key'),
      '#description' => $this->t('Obtain an API key from RWGPS to enable retrieval of RWGPS route information.'),
      '#default_value' => $config->get('rwgps_api'),
    ];

    return parent::buildForm($form, $form_state);
  }

   /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('bikeclub.adminsettings')
      ->set('rwgps_api', $form_state->getValue('rwgps_api'))
      ->save();
  }
}
