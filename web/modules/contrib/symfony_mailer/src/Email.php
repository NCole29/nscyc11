<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Component\Utility\Html;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AssetOptimizerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\symfony_mailer\Processor\CallbackEmailProcessor;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * Defines the email class.
 */
class Email extends BaseEmail implements InternalEmailInterface {

  use AutowireTrait;

  /**
   * The unrendered body array.
   */
  protected array $body = [];

  /**
   * The processors.
   */
  protected array $processors = [];

  /**
   * The processors remaining to process in the current phase.
   */
  protected array $processorQueue = [];

  /**
   * Set to TRUE to re-sort the processor queue.
   */
  protected bool $processorSort = FALSE;

  /**
   * The language code.
   */
  protected string $langcode;

  /**
   * The params.
   *
   * @var string[]
   */
  protected array $params = [];

  /**
   * The variables.
   *
   * @var string[]
   */
  protected array $variables = [];

  /**
   * The account for the recipient (can be anonymous).
   */
  protected AccountInterface $account;

  /**
   * The theme.
   */
  protected string $theme = '';

  /**
   * The libraries.
   */
  protected array $libraries = [];

  /**
   * The error message from sending.
   */
  protected string $errorMessage = '';

  /**
   * Constructs the Email object.
   *
   * @param \Drupal\symfony_mailer\MailerPlusInterface $mailer
   *   Mailer service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Asset\AssetResolverInterface $assetResolver
   *   The asset resolver.
   * @param \Drupal\Core\Asset\AssetOptimizerInterface $cssOptimizer
   *   The Drupal CSS optimizer.
   * @param string $tag
   *   Tag used to identify the type or source of this email.
   *   @see self::getTag()
   *
   * @internal
   */
  public function __construct(
    protected readonly MailerPlusInterface $mailer,
    protected readonly RendererInterface $renderer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ThemeManagerInterface $themeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AssetResolverInterface $assetResolver,
    #[Autowire(service: 'asset.css.optimizer')]
    protected readonly AssetOptimizerInterface $cssOptimizer,
    protected readonly string $tag,
  ) {
    $this->inner = new SymfonyEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function getPhase(): int {
    return $this->phase;
  }

  /**
   * {@inheritdoc}
   */
  public function addProcessor(EmailProcessorInterface $processor): static {
    $this->valid(self::PHASE_INIT);
    $this->processors[$processor->getId()] = $processor;
    $this->processorQueue[] = $processor;
    $this->processorSort = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addCallback(callable $function, int $phase = self::PHASE_BUILD, int $weight = self::DEFAULT_WEIGHT, ?string $id = NULL): static {
    $this->addProcessor((new CallbackEmailProcessor($weight, $id))->setCallback($function, $phase));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeProcessor(string $id): static {
    $this->valid(self::PHASE_INIT);
    unset($this->processors[$id]);
    $this->processorQueue = array_filter($this->processorQueue, fn(EmailProcessorInterface $processor) => ($processor->id() != $id));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessors(): array {
    return $this->processors;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function setParams(array $params = []): static {
    $this->valid(min_phase: self::PHASE_INIT);
    $this->params = $params;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setParam(string $key, $value): static {
    $this->valid(min_phase: self::PHASE_INIT);
    $this->params[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityParam(EntityInterface $entity): static {
    return $this->setParam($entity->getEntityTypeId(), $entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getParams(): array {
    return $this->params;
  }

  /**
   * {@inheritdoc}
   */
  public function getParam(string $key) {
    return $this->params[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function send(): bool {
    $this->valid(self::PHASE_INIT);
    return $this->mailer->send($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function setBody(array $body): static {
    $this->valid(self::PHASE_BUILD);
    $this->body = $body;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBody(): array {
    $this->valid(self::PHASE_BUILD);
    return $this->body;
  }

  /**
   * {@inheritdoc}
   */
  public function setVariables(array $variables): static {
    $this->valid(self::PHASE_BUILD, self::PHASE_INIT);
    $this->variables = $variables;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setVariable(string $key, $value): static {
    $this->valid(self::PHASE_BUILD, self::PHASE_INIT);
    $this->variables[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityVariable(string $key, $view_mode = 'email'): static {
    $this->valid(self::PHASE_BUILD);
    $entity = $this->getParam($key);
    $build = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode);
    $this->setVariable($key, $build);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * {@inheritdoc}
   */
  public function getTag(?int $part = NULL): string {
    if (is_null($part)) {
      return $this->tag;
    }
    return explode('.', $this->tag)[$part] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestions(string $initial, string $join): array {
    $part_array = explode('.', $this->tag);
    $part = $initial ?: array_shift($part_array);
    $suggestions[] = $part;

    while ($part_array) {
      $part .= $join . array_shift($part_array);
      $suggestions[] = $part;
    }

    return $suggestions;
  }

  /**
   * {@inheritdoc}
   */
  public function setTheme(string $theme_name): static {
    $this->valid(self::PHASE_INIT);
    $this->theme = $theme_name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTheme(): string {
    if (!$this->theme) {
      $this->theme = $this->themeManager->getActiveTheme()->getName();
    }
    return $this->theme;
  }

  /**
   * {@inheritdoc}
   */
  public function addLibrary(string $library): static {
    $this->valid(self::PHASE_BUILD);
    $this->libraries[] = $library;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return $this->libraries;
  }

  /**
   * {@inheritdoc}
   */
  public function setTransport(string $transport): static {
    $this->valid();
    $this->getHeaders()->addHeader('X-Transport', $transport);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransport(): string {
    return $this->getHeaders()->getHeaderBody('X-Transport');
  }

  /**
   * {@inheritdoc}
   */
  public function getCss(): string {
    $assets = (new AttachedAssets())->setLibraries($this->getLibraries());
    $css = '';
    foreach ($this->assetResolver->getCssAssets($assets, FALSE) as $asset) {
      if (($asset['type'] == 'file') && $asset['preprocess']) {
        // Optimize to process @import.
        $css .= $this->cssOptimizer->optimize($asset);
      }
      else {
        $css .= file_get_contents($asset['data']);
      }
    }

    return $css;
  }

  /**
   * {@inheritdoc}
   */
  public function getError(): string {
    return $this->errorMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function process(): static {
    $this->processorQueue = $this->getProcessors();
    $this->processorSort = TRUE;

    // While processing PHASE_INIT, each processor may add or remove others.
    // Therefore we use a queue and a while loop rather than a for loop.
    while ($this->processorQueue) {
      if ($this->processorSort) {
        usort($this->processorQueue, function ($a, $b) {
          return $a->getWeight($this->phase) <=> $b->getWeight($this->phase);
        });
        $this->processorSort = FALSE;
      }

      $processor = array_shift($this->processorQueue);

      switch ($this->phase) {
        case self::PHASE_INIT:
          $processor->init($this);
          break;

        case self::PHASE_BUILD:
          $processor->build($this);
          break;

        case self::PHASE_POST_RENDER:
          $processor->postRender($this);
          break;

        case self::PHASE_POST_SEND:
          $processor->postSend($this);
          break;
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function customize(string $langcode, AccountInterface $account): static {
    $this->valid(self::PHASE_INIT);
    $this->langcode = $langcode;
    $this->account = $account;
    $this->phase = self::PHASE_BUILD;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): static {
    $this->valid(self::PHASE_BUILD);

    $body = ['#theme' => 'email', '#email' => $this];
    $html = $this->renderer->renderInIsolation($body);
    $this->phase = self::PHASE_POST_RENDER;
    $this->setHtmlBody((string) $html);
    $this->body = [];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSymfonyEmail(): SymfonyEmail {
    $this->valid(min_phase: self::PHASE_POST_RENDER);
    $this->phase = self::PHASE_POST_SEND;

    // Addresses.
    $headers = $this->getHeaders();
    foreach ($this->addresses as $name => $addresses) {
      $value = [];
      foreach ($addresses as $address) {
        $value[] = $address->getSymfony();
      }
      if ($value) {
        if ($name === 'sender') {
          $this->inner->sender($value[0]);
          continue;
        }

        $headers->addMailboxListHeader($name, $value);
      }
    }

    // Attachments.
    foreach ($this->attachments as $attachment) {
      if ($attachment->hasAccess()) {
        $this->inner->addPart($attachment);
        if (($attachment->getMediaType() == 'image') && ($attachment->getUri() != NULL)) {
          $replace_uri = TRUE;
        }
      }
    }

    if (isset($replace_uri) && $body = $this->getHtmlBody()) {
      $dom = Html::load($body);

      foreach ($dom->getElementsByTagName('img') as $img) {
        $uri = $img->getAttribute('src');

        if ($attach = $this->attachments[$uri] ?? NULL) {
          $img->setAttribute('src', 'cid:' . $attach->getContentId());
        }
      }

      $body = Html::serialize($dom);
      $this->phase = self::PHASE_POST_RENDER;
      $this->setHtmlBody($body);
      $this->phase = self::PHASE_POST_SEND;
    }

    return $this->inner;
  }

  /**
   * {@inheritdoc}
   */
  public function setError(string $error): static {
    $this->valid(self::PHASE_POST_SEND, self::PHASE_POST_SEND);
    $this->errorMessage = $error;
    return $this;
  }

}
