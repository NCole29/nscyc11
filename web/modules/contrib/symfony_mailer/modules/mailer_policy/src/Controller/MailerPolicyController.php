<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mailer_policy\Entity\MailerPolicy;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Route controller for mailer override.
 */
class MailerPolicyController extends ControllerBase {

  /**
   * Creates a policy and redirects to the edit page.
   *
   * @param string $policy_id
   *   The policy ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the policy edit page.
   */
  public function createPolicy(string $policy_id, ?Request $request = NULL): RedirectResponse {
    MailerPolicy::create(['id' => $policy_id])->save();
    $options = [];
    $query = $request->query;
    if ($query->has('destination')) {
      $options['query']['destination'] = $query->get('destination');
      $query->remove('destination');
    }
    return $this->redirect('entity.mailer_policy.edit_form', ['mailer_policy' => $policy_id], $options);
  }

}
