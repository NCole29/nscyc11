<?php

declare(strict_types=1);

namespace Drupal\mailer_transport\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mailer_transport\TransportInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Route controller for mailer transport.
 */
class MailerTransportController extends ControllerBase {

  /**
   * Sets the transport as the default.
   *
   * @param \Drupal\mailer_transport\TransportInterface $mailer_transport
   *   The mailer transport entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the transport listing page.
   */
  public function setAsDefault(TransportInterface $mailer_transport): RedirectResponse {
    $mailer_transport->setAsDefault();
    $this->messenger()->addStatus($this->t('The default transport is now %label.', ['%label' => $mailer_transport->label()]));
    return $this->redirect('entity.mailer_transport.collection');
  }

}
