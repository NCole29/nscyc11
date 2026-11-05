<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mailer_policy\PolicyHelperInterface;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\Component\VerifyMailerInterface;

/**
 * Symfony Mailer verification email form.
 */
class VerifyEmailForm extends FormBase {

  use AutowireTrait;

  /**
   * Constructs a new VerifyEmailForm.
   *
   * @param \Drupal\symfony_mailer\Component\VerifyMailerInterface $mailer
   *   The verification mailer.
   * @param \Drupal\mailer_policy\PolicyHelperInterface $helper
   *   The policy helper.
   *
   * @internal
   */
  public function __construct(
    protected readonly VerifyMailerInterface $mailer,
    protected readonly ?PolicyHelperInterface $helper = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'symfony_mailer_verify_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;

    $form['recipient'] = [
      '#title' => $this->t('Recipient'),
      '#type' => 'textfield',
      '#default_value' => '',
      '#description' => $this->t('Recipient email address. Leave blank to send to yourself.'),
    ];

    if ($this->helper) {
      $form['mailer_policy'] = $this->helper->renderPolicy('symfony_mailer');
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $to = $form_state->getValue('recipient') ?: $this->currentUser();
    $this->mailer->verify($to);
    $message = is_object($to) ?
      $this->t('An attempt has been made to send an email to you.') :
      $this->t('An attempt has been made to send an email to @to.', ['@to' => $to]);
    $this->messenger()->addMessage($message);
  }

}
