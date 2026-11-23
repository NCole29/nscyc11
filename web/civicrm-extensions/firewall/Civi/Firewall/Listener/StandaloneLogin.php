<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */
namespace Civi\Firewall\Listener;

use Civi\Core\Service\AutoSubscriber;
use Civi\Firewall\Event\StandaloneLoginEvent;
use Civi\Firewall\Firewall;
use Civi\Standalone\Event\LoginEvent;

class StandaloneLogin extends AutoSubscriber {

  /**
   * @return array
   */
  public static function getSubscribedEvents(): array {
    return [
      'civi.standalone.login' => [
        // Positive priority is higher (eg. 200 will run before 100)
        ['onTrigger', 200],
      ],
    ];
  }

  /**
   * The standaloneusers issues a LoginEvent.
   *
   * We are only interested in the post_credentials_check stage here.
   *
   * @param \Civi\Standalone\Event\LoginEvent $event
   */
  public function onTrigger(LoginEvent $event) {
    if ($event->stage !== 'post_credentials_check') {
      return;
    }

    $ipAddress = Firewall::getIPAddress();
    $source = $event->stopReason;
    \Civi\Firewall\Event\StandaloneLoginEvent::trigger($ipAddress, $source);

    \Civi\Api4\FirewallIpaddress::create()
      ->setCheckPermissions(FALSE)
      ->addValue('ip_address', $ipAddress)
      ->addValue('source', $source)
      ->addValue('event_type', StandaloneLoginEvent::EVENT_TYPE)
      ->execute();
  }

}
