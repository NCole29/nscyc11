<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mailer_override\OverrideManagerInterface;

/**
 * Route controller for mailer override.
 */
class MailerOverrideController extends ControllerBase {

  /**
   * Constructs the MailerCommands object.
   *
   * @param \Drupal\mailer_override\OverrideManagerInterface $overrideManager
   *   The override manager.
   *
   * @internal
   */
  public function __construct(protected readonly OverrideManagerInterface $overrideManager) {}

  /**
   * Returns a page about override management status.
   *
   * @return array
   *   Render array.
   */
  public function overrideStatus() {
    $info = $this->overrideManager->getInfo();
    if ($info) {
      $info[OverrideManagerInterface::ALL_OVERRIDES] = $this->overrideManager->getInfo(OverrideManagerInterface::ALL_OVERRIDES);
    }

    $build = [
      '#type' => 'table',
      '#header' => [
        'name' => $this->t('Name'),
        'state_name' => $this->t('State'),
        'import' => $this->t('Import'),
        'operations' => $this->t('Operations'),
      ],
      '#rows' => $info,
      '#empty' => $this->t('There are no overrides available.'),
    ];

    foreach ($build['#rows'] as $id => &$row) {
      $operations = [];

      // Calculate the available operations.
      foreach ($row['action_names'] as $action => $label) {
        if ($label) {
          $operations[$action] = [
            'title' => $label,
            'url' => Url::fromRoute('mailer_override.action', ['action' => $action, 'id' => $id]),
          ];
        }
      }

      $row['operations']['data'] = [
        '#type' => 'operations',
        '#links' => $operations,
      ];

      if ($row['warning']) {
        // Combine the warning into the name column.
        $row['name'] = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ name }}<br><em>Warning: {{ warning }}</em>',
            '#context' => $row,
          ],
        ];
      }

      if ($row['import_warning']) {
        // Combine the import warning into the import column.
        $row['import'] = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ import }}<br><em>Warning: {{ import_warning }}</em>',
            '#context' => $row,
          ],
        ];
      }

      // Remove any extra keys.
      $row = array_intersect_key($row, $build['#header']);
    }

    return $build;
  }

}
