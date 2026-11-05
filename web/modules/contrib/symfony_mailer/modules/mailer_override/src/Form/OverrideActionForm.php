<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\mailer_override\OverrideManagerInterface;
use Drupal\symfony_mailer\AutowireTrait;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form to confirm an override action.
 */
class OverrideActionForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * The override ID.
   */
  protected string $id;

  /**
   * The action to execute.
   */
  protected string $action;

  /**
   * Human-readable label for the action.
   */
  protected TranslatableMarkup $actionName;

  /**
   * Human-readable description for the action.
   */
  protected TranslatableMarkup $description;

  /**
   * Human-readable string arguments to use for translation.
   *
   * @var string[]
   */
  protected array $args;

  /**
   * Constructs a new OverrideActionForm object.
   *
   * @param \Drupal\mailer_override\OverrideManagerInterface $overrideManager
   *   The override manager.
   *
   * @internal
   */
  public function __construct(protected readonly OverrideManagerInterface $overrideManager) {}

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return ($this->id == OverrideManagerInterface::ALL_OVERRIDES) ?
      $this->t('Are you sure you want to do %action for all overrides?', $this->args) :
      $this->t('Are you sure you want to do %action for override %name?', $this->args);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('mailer_override.status');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->actionName;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mailer_override_action_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $id
   *   The override ID.
   * @param string $action
   *   The action to execute.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $id = '', string $action = ''): array {
    $this->id = $id;
    $this->action = $action;
    $info = $this->overrideManager->getInfo($id);
    $this->actionName = $info['action_names'][$action] ?? NULL;
    if (!$this->actionName) {
      throw new NotFoundHttpException();
    }
    $this->args = ['%name' => $info['name'], '%action' => $this->actionName];

    // Use the last warning as the description.
    $warnings = $this->overrideManager->action($id, $action, TRUE);
    $disabled = empty($warnings);
    $this->description = $warnings ? array_pop($warnings) : $this->t('No available actions');
    $form['warnings'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Warnings'),
      '#items' => $warnings,
      '#access' => !empty($warnings),
    ];

    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#attributes']['disabled'] = $disabled;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->overrideManager->action($this->id, $this->action);
    $message = ($this->id == OverrideManagerInterface::ALL_OVERRIDES) ?
      $this->t('Completed %action for all overrides', $this->args) :
      $this->t('Completed %action for override %name', $this->args);
    $this->messenger()->addStatus($message);
    $this->logger('symfony_mailer')->notice($message);
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
