<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Defines the Theme Email Adjuster.
 */
#[EmailAdjuster(
  id: "email_theme",
  label: new TranslatableMarkup("Theme"),
  description: new TranslatableMarkup("Sets the email theme."),
)]
class ThemeEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  use AutowireTrait;

  /**
   * Constructs a new ThemeEmailAdjuster object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   The theme handler.
   * @param mixed ...$args
   *   Parent constructor arguments.
   *
   * @internal
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly ThemeHandlerInterface $themeHandler,
    ...$args,
  ) {
    parent::__construct(...$args);
  }

  /**
   * {@inheritdoc}
   */
  public function init(EmailInterface $email): void {
    $theme_name = $this->getEmailTheme();
    $email->setTheme($theme_name);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#description' => $this->t('Select the theme that will be used to render emails which are configured for this. This can be either the default theme, the active theme with fallback to default theme (if the active theme is the admin theme) or any enabled theme.'),
      '#options' => $this->getThemes(),
      '#required' => TRUE,
      '#default_value' => $this->configuration['theme'] ?? NULL,
    ];

    return $form;
  }

  /**
   * Returns a list of theme options.
   *
   * @return string[]
   *   The theme options.
   */
  protected function getThemes(): array {
    $options = [
      '_default' => $this->t('Default'),
      '_active_fallback' => $this->t('Active with fallback'),
    ];

    foreach ($this->themeHandler->listInfo() as $name => $theme) {
      if ($theme->status) {
        $options[$name] = $theme->info['name'];
      }
    }

    return $options;
  }

  /**
   * Returns the name of the theme to render the email.
   */
  protected function getEmailTheme(): string {
    $theme = $this->configuration['theme'];
    $theme_config = $this->configFactory->get('system.theme');

    switch ($theme) {
      case '_default':
        $theme = $theme_config->get('default');
        break;

      case '_active_fallback':
        $theme = $this->themeManager->getActiveTheme()->getName();
        if ($theme == $theme_config->get('admin')) {
          $theme = $theme_config->get('default');
        }
        break;
    }

    return $theme;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string {
    return (string) $this->getThemes()[$this->configuration['theme']];
  }

}
