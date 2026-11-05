<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mailer_transport\TransportUIManagerInterface;
use Drupal\mailer_transport\AutowireTrait;

/**
 * Provides a form with a mailer transport add button.
 */
class TransportAddButtonForm extends FormBase {

  use AutowireTrait;

  /**
   * Constructs a new TransportAddButtonForm.
   *
   * @param \Drupal\mailer_transport\TransportUIManagerInterface $manager
   *   The mailer transport plugin manager.
   *
   * @internal
   */
  public function __construct(protected readonly TransportUIManagerInterface $manager) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mailer_transport_add_button';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach ($this->manager->getDefinitions() as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $form['plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Transport type'),
      '#empty_option' => $this->t('- Choose transport type -'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add transport'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect(
      'entity.mailer_transport.add_form',
      ['plugin_id' => $form_state->getValue('plugin')]
    );
  }

}
