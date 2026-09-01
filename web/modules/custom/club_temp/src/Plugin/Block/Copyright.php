<?php

namespace Drupal\club_temp\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Copyright' Block.
 *
 * @Block(
 *   id = "club_copyright",
 *   admin_label = @Translation("Club copyright"),
 *   category = @Translation("Club"),
 * )
 */
class Copyright extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $year = date("Y");
    $config = \Drupal::service('config.factory')->getEditable('system.site');
    $site_name = $config->get('name');

    $copyright = t('©@year @site.  All Rights Reserved.', [
    '@year' => $year,
    '@site' => $site_name,
  ]);

    return [
      '#markup' => '<p>' . $copyright . '</p>',
    ];
  }

}
