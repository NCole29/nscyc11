<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\mailer_transport\MissingTransportException;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\automatic\AttachmentAccessEmailProcessor;
use Drupal\symfony_mailer\Processor\automatic\DefaultsEmailProcessor;
use Drupal\symfony_mailer\Processor\automatic\HooksEmailProcessor;
use Drupal\symfony_mailer\Processor\automatic\ReplacementEmailProcessor;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a enhanced Mailer service based on Symfony Mailer.
 */
class MailerPlus implements MailerPlusInterface {

  use StringTranslationTrait;

  /**
   * Email processors to add to all emails that are sent.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailProcessorInterface[]
   */
  protected array $processors = [];

  /**
   * Constructs the Mailer object.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Language\LanguageDefault $languageDefault
   *   The default language.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Theme\ThemeInitializationInterface $themeInitialization
   *   The theme initialization.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher service.
   * @param \Symfony\Component\Mailer\MailerInterface $delivery
   *   The mailer delivery service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Mail\MailManagerInterface $legacyMailManager
   *   The legacy mail manager service.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @internal
   */
  public function __construct(
    protected readonly EventDispatcherInterface $dispatcher,
    protected readonly RendererInterface $renderer,
    protected readonly LanguageDefault $languageDefault,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly AccountInterface $account,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly ThemeInitializationInterface $themeInitialization,
    protected readonly AccountSwitcherInterface $accountSwitcher,
    #[Autowire(service: 'mailer_transport.delivery')]
    protected readonly ?MailerInterface $delivery,
    protected readonly MessengerInterface $messenger,
    protected readonly MailManagerInterface $legacyMailManager,
    protected readonly ContainerInterface $container,
  ) {
    $this->addProcessor(new DefaultsEmailProcessor())
      ->addProcessor(HooksEmailProcessor::create($container))
      ->addProcessor(ReplacementEmailProcessor::create($container))
      ->addProcessor(AttachmentAccessEmailProcessor::create($container));
  }

  /**
   * {@inheritdoc}
   */
  public function newEmail(string $tag): EmailInterface {
    $email = Email::create($this->container, $tag);
    foreach ($this->processors as $processor) {
      $email->addProcessor($processor);
    }
    return $email;
  }

  /**
   * {@inheritdoc}
   */
  public function send(InternalEmailInterface $email): bool {
    // Mailing can invoke rendering (e.g., generating URLs, replacing tokens),
    // but e-mails are not HTTP responses: they're not cached, they don't have
    // attachments. Therefore we perform mailing inside its own render context,
    // to ensure it doesn't leak into the render context for the HTTP response
    // to the current request.
    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($email) {
      try {
        return $this->doSend($email);
      }
      catch (SkipMailException $e) {
        if ($this->account->hasPermission('administer mailer')) {
          $this->messenger->addMessage($this->t('Email sending skipped: %message.', [
            '%message' => $e->getMessage(),
          ]));
        }
        return TRUE;
      }
    });
  }

  /**
   * Sends an email.
   *
   * @param \Drupal\symfony_mailer\InternalEmailInterface $email
   *   The email to send.
   *
   * @return bool
   *   Whether successful.
   *
   * @internal
   */
  public function doSend(InternalEmailInterface $email): bool {
    // Process the init phase.
    // @see \Drupal\symfony_mailer\EmailInterface::PHASE_INIT
    $email->process();

    // Determine langcode and account from the to address, if there is
    // agreement.
    $langcodes = $accounts = [];
    foreach ($email->getTo() as $to) {
      if ($loop_langcode = $to->getLangcode()) {
        $langcodes[$loop_langcode] = $loop_langcode;
      }
      if ($loop_account = $to->getAccount()) {
        $accounts[$loop_account->id()] = $loop_account;
      }
    }
    $langcode = (count($langcodes) == 1) ? reset($langcodes) : $this->languageManager->getDefaultLanguage()->getId();
    $account = (count($accounts) == 1) ? reset($accounts) : User::getAnonymousUser();
    $email->customize($langcode, $account);

    // Do switching.
    $active_theme_name = $this->changeTheme($email->getTheme());

    $must_switch_account = $account->id() != $this->account->id();
    if ($must_switch_account) {
      $this->accountSwitcher->switchTo($account);
    }

    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    $must_switch_language = $langcode !== $current_langcode;
    if ($must_switch_language) {
      $this->changeActiveLanguage($langcode);
    }

    try {
      // Process the build phase.
      // @see \Drupal\symfony_mailer\EmailInterface::PHASE_BUILD
      $email->process();

      // Render.
      $email->render();

      // Process the post-render phase.
      // @see \Drupal\symfony_mailer\EmailInterface::PHASE_POST_RENDER
      $email->process();
    }
    finally {
      // Switch back.
      if ($must_switch_account) {
        $this->accountSwitcher->switchBack();
      }

      if ($must_switch_language) {
        $this->changeActiveLanguage($current_langcode);
      }

      $this->changeTheme($active_theme_name);
    }

    if ($this->delivery) {
      try {
        // Send via Symfony Mailer library.
        $symfony_email = $email->getSymfonyEmail();

        // ksm($email, $symfony_email->getHeaders());
        $this->delivery->send($symfony_email);
      }
      catch (\Exception $e) {
        if ($e instanceof MissingTransportException) {
          $email->setError((string) Markup::create($e->getMessage() . ' ' . $this->t('Please <a href=":url">check configuration</a>.', [
            ':url' => Url::fromRoute('entity.mailer_transport.collection')->toString(),
          ])));
        }
        else {
          $email->setError($e->getMessage());
        }
      }
    }
    else {
      // Send via legacy Mail System.
      $message = $this->legacyMailManager->mail($email->getTag(0),
        $email->getTag(1),
        implode(',', array_map(fn($a) => $a->toString(), $email->getTo())),
        $langcode,
        ['__email' => $email],
        implode(',', array_map(fn($a) => $a->toString(), $email->getReplyTo())),
      );

      // ksm($message);
      if ($message['result'] == FALSE) {
        $email->setError($this->t('Error sending email'));
      }
    }

    if ($error = $email->getError()) {
      // Log.
      $params = ['%message' => $error];
      $this->loggerFactory->get('symfony_mailer')->error('Error sending email: %message', $params);

      // Messenger.
      if (!$this->account->hasPermission('administer mailer')) {
        // Hide the detailed message and show a generic one instead.
        $error = $this->t('Unable to send email. Contact the site administrator if the problem persists.');
      }

      $this->messenger->addError($error);
    }

    // Process the post-send phase.
    // @see \Drupal\symfony_mailer\EmailInterface::PHASE_POST_SEND
    $email->process();

    return empty($error);
  }

  /**
   * {@inheritdoc}
   */
  public function addProcessor(EmailProcessorInterface $processor): static {
    $this->processors[] = $processor;
    return $this;
  }

  /**
   * Changes the active theme.
   *
   * @param string $theme_name
   *   The theme name to change to.
   *
   * @return string
   *   The previously active theme name.
   */
  protected function changeTheme(string $theme_name): string {
    $active_theme_name = $this->themeManager->getActiveTheme()->getName();
    if ($theme_name !== $active_theme_name) {
      $this->themeManager->setActiveTheme($this->themeInitialization->initTheme($theme_name));
    }

    return $active_theme_name;
  }

  /**
   * Changes the active language for translations.
   *
   * @param string $langcode
   *   The langcode.
   */
  protected function changeActiveLanguage(string $langcode): void {
    // Language switching adapted from commerce module.
    // @see \Drupal\commerce\MailHandler::sendMail
    if (!$this->languageManager->isMultilingual()) {
      return;
    }

    $language = $this->languageManager->getLanguage($langcode);
    if (!$language) {
      return;
    }
    // The language manager has no method for overriding the default language,
    // like it does for config overrides. We have to change the default
    // language service's current language.
    // @see https://www.drupal.org/project/drupal/issues/2410579
    $this->languageDefault->set($language);
    $this->languageManager->setConfigOverrideLanguage($language);
    $this->languageManager->reset();

    // The default string_translation service, TranslationManager, has a
    // setDefaultLangcode method. However, this method is not present on either
    // of its interfaces. Therefore we check for the concrete class here so
    // that any swapped service does not break the application.
    // @see https://www.drupal.org/project/drupal/issues/3029003
    $string_translation = $this->getStringTranslation();
    if ($string_translation instanceof TranslationManager) {
      $string_translation->setDefaultLangcode($language->getId());
      $string_translation->reset();
    }
  }

}
