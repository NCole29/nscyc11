<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer\Processor\automatic;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\symfony_mailer\AutowireTrait;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorInterface;
use Drupal\symfony_mailer\Processor\EmailProcessorTrait;

/**
 * Defines the Attachment access Email Processor.
 */
class AttachmentAccessEmailProcessor implements EmailProcessorInterface {

  use AutowireTrait;
  use EmailProcessorTrait;

  /**
   * The allowed schemes for the attachment URI.
   *
   * The value is a boolean:
   * - TRUE: check the URI can be opened.
   * - FALSE: no checking required.
   */
  protected const ALLOWED_SCHEMES = [
    'http' => TRUE,
    'https' => TRUE,
    'public' => FALSE,
    '_data_' => FALSE,
  ];

  /**
   * AttachmentAccessEmailProcessor constructor.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    // Grant access if the file would be available for direct download by the
    // recipient.
    foreach ($email->getAttachments() as $attachment) {
      $uri = $attachment->getUri();
      $scheme = $uri ? parse_url($uri, PHP_URL_SCHEME) : '_data_';
      $check = self::ALLOWED_SCHEMES[$scheme] ?? NULL;

      if (!is_null($check)) {
        if ($check) {
          $handle = @fopen($uri, 'r');
          if (!$handle) {
            continue;
          }
          fclose($handle);
        }
        $attachment->setAccess(AccessResult::allowed());
      }
      elseif ($this->streamWrapperManager->isValidScheme($scheme) && is_file($uri)) {
        // Based on FileDownloadController::download().
        $headers = $this->moduleHandler->invokeAll('file_download', [$uri]);
        if (in_array(-1, $headers)) {
          // The -1 value indicates that the file is not available for
          // download, and this takes precedence over a header from another
          // hook (as per FileDownloadController).
          continue;
        }

        if (count($headers)) {
          $attachment->setAccess(AccessResult::allowed());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(int $phase): int {
    return 1000;
  }

}
