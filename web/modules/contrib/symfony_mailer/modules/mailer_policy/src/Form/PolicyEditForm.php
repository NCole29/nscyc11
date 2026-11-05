<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Mailer policy edit form.
 */
class PolicyEditForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /*
     * @todo Display the tag and entity.
     * @todo Display the inherited adjusters and provide a way to block them.
     * @todo Don't offer to add an inherited, not configurable adjuster.
     * @todo Show an adjuster description.
     */

    // Get the adjusters and synchronise with any existing form state.
    /** @var \Drupal\mailer_policy\Entity\MailerPolicy $policy; */
    $policy = $this->entity;
    $adjusters = $policy->adjusters();
    $config = $form_state->getValue('config', []);
    if ($config) {
      $adjusters->setConfiguration($config);
    }

    // Read the mailer definition.
    $mailer_definition = $policy->getMailerDefinition();
    $inherited_config = array_keys(MailerPolicy::loadInheritedConfig($policy->id(), FALSE));
    $required_config = array_diff($mailer_definition['required_config'], $inherited_config);
    $missing_config = array_diff($required_config, $adjusters->getInstanceIds());

    // Detect if this is a "final" policy, with no possibility of being
    // overridden. In this case, it must provide all required config.
    $final = !$mailer_definition['sub_defs'] && ($policy->getEntity() || !$mailer_definition['metadata_key']);

    if ($final) {
      // Pre-populate adjusters for any required config that is missing.
      foreach ($missing_config as $missing_id) {
        $adjusters->addInstanceId($missing_id, []);
      }
    }

    // Set a div to allow updating the entire form when the type is changed.
    $form['#prefix'] = '<div id="mailer-policy-edit-form">';
    $form['#suffix'] = '</div>';

    $form['label'] = [
      '#markup' => $policy->label(),
      '#prefix' => '<h2>',
      '#suffix' => '</h2>',
      '#weight' => -2,
    ];

    $ajax = [
      'callback' => '::ajaxUpdate',
      'wrapper' => 'mailer-policy-edit-form',
    ];

    // Add adjuster button.
    $form['add_actions'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => ['class' => ['container-inline']],
    ];

    // Put the required adjusters first.
    $options = $options2 = [];
    foreach ($policy->adjusterDefinitions() as $name => $definition) {
      if (!$adjusters->has($name)) {
        if (in_array($name, $mailer_definition['required_config'])) {
          $options[$name] = $definition['label'];
        }
        else {
          $options2[$name] = $definition['label'];
        }
      }
    }
    asort($options);
    asort($options2);
    $options += $options2;

    $form['add_actions']['add_select'] = [
      '#type' => 'select',
      '#options' => $options,
      '#empty_value' => '',
      '#empty_option' => $this->t('- Select element to add -'),
    ];

    $form['add_actions']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add element'),
      '#submit' => ['::submitAdd'],
      '#ajax' => $ajax,
    ];

    // Help.
    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Replacement patterns'),
    ];

    // Variables.
    foreach ($mailer_definition['variables'] as $key => $value) {
      $items[] = "{{ $key }}  $value";
    }
    if (isset($items)) {
      $form['help']['variables'] = [
        '#prefix' => $this->t("Available variables:"),
        '#theme' => 'item_list',
        '#items' => $items,
      ];
    }

    // Tokens.
    if ($token_types = $mailer_definition['token_types']) {
      if (\Drupal::service('module_handler')->moduleExists('token')) {
        $form['help']['token'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => $token_types,
          '#global_types' => FALSE,
        ];
      }
      else {
        $form['help']['token'] = [
          '#markup' => $this->t("The following token types are available for this policy: @types.", ['@types' => implode(', ', $token_types)]),
        ];
      }
    }

    // Hide help if empty.
    if (empty(Element::children($form['help']))) {
      $form['help']['#access'] = FALSE;
    }
    else {
      $form_state->setValue('mailer_policy_help', ' ' . $this->t('This field supports <em>replacement patterns</em> (see above).'));
    }

    // Main adjuster config.
    $form['config'] = [
      '#type' => 'container',
    ];

    foreach ($adjusters->sort() as $name => $adjuster) {
      $required = $final && in_array($name, $required_config);

      $form['config'][$name] = [
        '#type' => 'details',
        '#title' => $adjuster->getLabel(),
        '#tree' => TRUE,
        '#open' => TRUE,
        '#parents' => ['config', $name],
        '#required' => $required,
      ];

      $form['config'][$name] += $adjuster->settingsForm([], $form_state);

      $form['config'][$name]['remove_button'] = [
        '#type' => 'submit',
        '#name' => "remove_$name",
        '#value' => $this->t('Remove'),
        '#submit' => ['::submitRemove'],
        '#ajax' => $ajax,
        '#disabled' => $required,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Update the mailer policy configuration and save.
    $this->entity->setConfiguration($form_state->getValue('config') ?? [])
      ->save();
  }

  /**
   * Ajax callback to update the form.
   */
  public static function ajaxUpdate($form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Submit callback for add button.
   */
  public static function submitAdd(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('add_select');
    $form_state->setValue(['config', $name], [])
      ->setRebuild();
  }

  /**
   * Submit callback for remove button.
   */
  public static function submitRemove(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $name = $button['#parents'][1];
    $form_state->unsetValue(['config', $name])
      ->setRebuild();
  }

}
