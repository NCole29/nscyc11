<?php

namespace Drupal\bikeclub_reports\Controller;

/**
 * @file
 * Landing page for reports menus.
 */
class ReportsPage {

  public function LandingPage() {

    $content = '<h3>Club Reports</h3>';
    $content .= 'The menu blocks on the left appear on all pages with the /report/ prefix';
    $content .= '<p>Additional reports may be added to these menus.';
    $content .= 'In Views, edit the View path to add the /report/ prefix.';
    $content .= "<br>Then provide a 'Normal menu entry' and select one of these menus as the 'Parent menu':<ul>";
    $content .= '<li>Club Reports</li>';
    $content .= '<li>Century reports</li>';
    $content .= '<li>Membership reports</li>';
    $content .= '<li>Other events</li></ul>';


    return [
      '#type' => '#markup',
      '#markup' => $content,
      '#attached' => [
        'library' => [
		  'bikeclub/bikeclub-style',
        ],
      ]
    ];

  }
}