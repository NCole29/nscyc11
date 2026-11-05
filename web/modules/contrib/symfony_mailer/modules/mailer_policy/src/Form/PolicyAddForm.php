<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mailer_policy\Entity\MailerPolicy;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\MailerLookupInterface;

/**
 * Mailer policy add form.
 */
class PolicyAddForm extends EntityForm {

  use AutowireTrait;

  /**
   * Constructs PolicyAddForm.
   *
   * @param \Drupal\symfony_mailer\MailerLookupInterface $mailerLookup
   *   The mailer lookup service.
   *
   * @internal
   */
  public function __construct(protected MailerLookupInterface $mailerLookup) {}

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Set a div to allow updating the entire form when the type is changed.
    $form['#prefix'] = '<div id="mailer-policy-add-form">';
    $form['#suffix'] = '</div>';
    $ajax = [
      'callback' => '::ajaxUpdate',
      'wrapper' => 'mailer-policy-add-form',
    ];

    // First part of tag.
    $options1 = [];
    $meta_key = '';
    foreach ($this->mailerLookup->getDefinitions() as $id => $definition) {
      $options1[$id] = $definition['label'];
    }
    asort($options1);

    $form['tag1'] = [
      '#type' => 'select',
      '#title' => $this->t('Tag 1'),
      '#description' => $this->t("First part of the email tag that the policy applies to."),
      '#options' => $options1,
      '#empty_value' => '',
      '#empty_option' => $this->t('<b>*All*</b>'),
      '#ajax' => $ajax,
    ];

    // This form is Ajax enabled, so fetch the existing values if present.
    if ($tag1 = $form_state->getValue('tag1')) {
      $definition = $this->mailerLookup->getDefinition($tag1);
      $meta_key = $definition['metadata_key'];

      // Second part of tag.
      $form['tag2'] = [
        '#title' => $this->t('Tag 2'),
        '#description' => $this->t("Second part of the email tag that the policy applies to."),
        '#type' => 'textfield',
      ];

      $options2 = [];
      foreach ($definition['sub_defs'] as $id => $sub_def) {
        // A legacy hook_mail() module exposes a single wildcard sub-type
        // ("*") for its unknown sub-definitions. Don't offer it as a
        // selectable option: "*" is an illegal character in a config name
        // and, being the only option, the select would force the user to
        // pick it. Skipping it here leaves the free text Tag 2 field in
        // place (blank means "all"), while keeping the "*" sub-definition
        // available to MailerLookup for label resolution.
        if ($id !== '*') {
          $options2[$id] = $sub_def['label'];
        }
      }
      asort($options2);

      if ($options2) {
        $form['tag2'] = [
          '#type' => 'select',
          '#options' => $options2,
          '#ajax' => $ajax,
        ] + $form['tag2'];

        if (count($options2) > 1) {
          $form['tag2']['#empty_value'] = '';
          $form['tag2']['#empty_option'] = $this->t('<b>*All*</b>');
        }

        if ($tag2 = $form_state->getValue('tag2')) {
          $sub_def = $definition['sub_defs'][$tag2];
          $meta_key = $sub_def['metadata_key'] ?: $meta_key;

          // Third part of tag.
          $options3 = [];
          foreach ($sub_def['sub_defs'] as $id => $sub_sub_def) {
            $options3[$id] = $sub_sub_def['label'];
          }
          asort($options3);

          if ($options3) {
            $form['tag3'] = [
              '#title' => $this->t('Tag 3'),
              '#description' => $this->t("Third part of the email tag that the policy applies to."),
              '#type' => 'select',
              '#options' => $options3,
              '#empty_value' => '',
              '#empty_option' => $this->t('<b>*All*</b>'),
              '#ajax' => $ajax,
            ];
          }
        }
      }
    }

    if ($meta_key) {
      // Entity.
      $entities = [];
      foreach ($this->entityTypeManager->getStorage($meta_key)->loadMultiple() as $id => $entity) {
        $entities[$id] = $entity->label();
      }
      asort($entities);

      $form['entity_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity'),
        '#description' => $this->t("Entity that the policy applies to, or leave empty for all entities."),
        '#options' => $entities,
        '#empty_value' => '',
        '#empty_option' => $this->t('<b>*All*</b>'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Build policy id.
    $id_array = [
      $form_state->getValue('tag1'),
      $form_state->getValue('tag2'),
      $form_state->getValue('tag3'),
    ];
    $id = implode('.', array_filter($id_array)) ?: '_';
    if ($entity_id = $form_state->getValue('entity_id')) {
      $id .= "..$entity_id";
    }
    $form_state->setValue('id', $id);

    // If the policy exists, throw an error.
    if (MailerPolicy::load($id)) {
      $url = Url::fromRoute('entity.mailer_policy.edit_form', ['mailer_policy' => $id])->toString();
      $form_state->setErrorByName('tag1', $this->t('Policy already exists (<a href=":url">edit</a>)', [':url' => $url]));
      $form_state->setErrorByName('tag2');
      $form_state->setErrorByName('tag3');
      $form_state->setErrorByName('entity_id');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->addCleanValueKey('tag1')
      ->addCleanValueKey('tag2')
      ->addCleanValueKey('tag3')
      ->addCleanValueKey('entity_id')
      ->setRedirect('entity.mailer_policy.edit_form', ['mailer_policy' => $form_state->getValue('id')]);
    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to update the form.
   */
  public static function ajaxUpdate($form, FormStateInterface $form_state) {
    // Return the entire form updated.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    $element = parent::actionsElement($form, $form_state);
    $element['submit']['#value'] = $this->t('Add and configure');
    return $element;
  }

}
