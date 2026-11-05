<?php

namespace Drupal\mailer_override_legacy_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\symfony_mailer\AutowireTrait;

/**
 * Test module form to send a test legacy email.
 */
class LegacyTestEmailForm extends FormBase {

  use AutowireTrait;

  /**
   * An email 'to' address to use in this form and all tests run.
   */
  const ADDRESS_TO = 'mailer_override-legacy_test-to@example.com';

  /**
   * An email 'cc' address to use in this form and all tests run.
   */
  const ADDRESS_CC = 'mailer_override-legacy_test-cc@example.com';

  /**
   * An email 'bcc' address to use in this form and all tests run.
   */
  const ADDRESS_BCC = 'mailer_override-legacy_test-bcc@example.com';

  /**
   * An email 'bcc' address to use in this form and all tests run.
   */
  const ADDRESS_REPLY_TO = 'mailer_override-legacy_test-reply-to@example.com';

  /**
   * An attachment to use in this form and all tests run.
   */
  const ATTACHMENT_URI = 'private://test.txt';

  /**
   * Constructs TestMailForm.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager service.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager service.
   *
   * @internal
   */
  public function __construct(protected readonly MailManagerInterface $mailManager, protected readonly ThemeManagerInterface $themeManager) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mailer_override_legacy_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Send test email',
    ];
    $current_theme = $this->themeManager->getActiveTheme()->getName();
    $form['current_theme'] = [
      '#markup' => 'Current theme: ' . $current_theme,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Attach a 'private:// ' file.
    $file = File::create(['uri' => self::ATTACHMENT_URI]);
    $file->save();
    file_put_contents($file->getFileUri(), 'Attachment contents');
    $params['attachments'][] = ['filepath' => self::ATTACHMENT_URI];

    $message = $this->mailManager->mail('mailer_override_legacy_test', 'legacy_test', 'wrong_value@example.com', 'fr', $params, 'wrong_value@example.com');

    // Test that the returned message array is correct.
    if ($message['reply-to'] != self::ADDRESS_REPLY_TO) {
      throw new \Exception('Wrong reply-to');
    }
    if ($message['headers']['Reply-to'] != self::ADDRESS_REPLY_TO) {
      throw new \Exception('Wrong Reply-to header');
    }
    if (isset($message['Reply-to'])) {
      throw new \Exception('Reply-to should not be set');
    }
    if (isset($message['Reply-To'])) {
      throw new \Exception('Reply-To should not be set');
    }
    if (isset($message['headers']['reply-to'])) {
      throw new \Exception('reply-to header should not be set');
    }
    if (isset($message['headers']['Reply-To'])) {
      throw new \Exception('Reply-To header should not be set');
    }
  }

}
