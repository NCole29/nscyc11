<?php
declare(strict_types = 1);

namespace Civi\Stripe;

use Civi\Core\Event\GenericHookEvent;
use Civi\Core\Service\AutoService;
use Civi\Stripe\CheckoutOption\StripeEmbeddedCheckout;
use Civi\Stripe\CheckoutOption\StripeHostedCheckout;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use CRM_Stripe_ExtensionUtil as E;

/**
 * This service provides CheckoutOptions based on Stripe connections
 *
 * It does nothing if civi.checkout.options event isn't fired
 * (ie if you dont have afform_payment)
 *
 * It may be useful to add status checks etc here - see Civi\Paypal\PaypalConnections
 *
 * @service stripe.connections
 */
class StripeConnections extends AutoService implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'civi.checkout.options' => 'getCheckoutOptions',
    ];
  }

  public function getCheckoutOptions(GenericHookEvent $e): void {
    foreach ($this->getPaymentProcessorPairs(['Stripe', 'StripeCheckout']) as $name => $pair) {
      // TODO: provide setting for configuring which checkout options are available for which connection
      $e->options['stripe_embedded_checkout_' . $name] = new StripeEmbeddedCheckout($pair['live'], $pair['test']);
      $e->options['stripe_hosted_checkout_' . $name] = new StripeHostedCheckout($pair['live'], $pair['test']);
    }
  }

  /**
   * @todo Replace with CheckoutOptionUtils::getPaymentProcessorPairs() when
   *   https://github.com/civicrm/civicrm-core/pull/36163 is merged and stripe
   *   min version is updated.
   */
  private function getPaymentProcessorPairs(array $paymentProcessorTypeNames): array {
    $all = \Civi\Api4\PaymentProcessor::get(FALSE)
      // note the payment_processor_type_id doesn't actually matter when it comes to
      // CheckoutOptions - as long as the credentials are valid
      ->addWhere('payment_processor_type_id:name', 'IN', $paymentProcessorTypeNames)
      ->addWhere('is_active', '=', TRUE)
      // otherwise Api4 excludes test processors
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute();

    $pairs = [];

    foreach ($all as $processor) {
      $pairs[$processor['name']][$processor['is_test'] ? 'test' : 'live'] = $processor;
    }

    return $pairs;
  }

}
