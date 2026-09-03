<?php

namespace Drupal\bikeclub\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
  * Class RouteSubscriber.
  *
  * @package Drupal\bikeclub\Routing
  */
  class RouteSubscriber extends RouteSubscriberBase {

 /**
  * {@inheritdoc}
  */
  protected function alterRoutes(RouteCollection $collection) {

    // Alter the path to the personal contact form .
    if ($route = $collection->get('entity.user.contact_form')) {
      $route->setPath('/contact/{user}/contact');
    }

    // Use admin theme.
    if ($route = $collection->get('view.club_contacts.admin') or
      $route = $collection->get('advanced_help.help')) {
        $route->setOption('_admin_route', TRUE);
    }

    // Custom access to webform submisssions.
    if ($route = $collection->get('entity.node.webform.results_submissions')) {
      $route->setRequirement('_custom_access', 'Drupal\bikeclub\Access\WebformSubmissionAccess::access');
    }
  }
}
