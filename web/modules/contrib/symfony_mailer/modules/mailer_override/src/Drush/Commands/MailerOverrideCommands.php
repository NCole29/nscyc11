<?php

declare(strict_types=1);

namespace Drupal\mailer_override\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\mailer_override\OverrideManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mailer override drush commands.
 */
class MailerOverrideCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs the MailerOverrideCommands object.
   *
   * @param \Drupal\mailer_override\OverrideManagerInterface $overrideManager
   *   The override manager.
   *
   * @internal
   */
  public function __construct(protected readonly OverrideManagerInterface $overrideManager) {}

  /**
   * Executes an override action.
   *
   * @param string $action
   *   Action to run: 'import', 'enable', or 'disable'.
   * @param string $id
   *   (optional) Override ID, or omit to execute all.
   *
   * @command mailer:override
   */
  public function override(string $action, string $id = OverrideManagerInterface::ALL_OVERRIDES): void {
    $info = $this->overrideManager->getInfo($id);
    $action_name = $info['action_names'][$action] ?? NULL;
    if (!$action_name) {
      throw new NotFoundHttpException();
    }

    $warnings = $this->overrideManager->action($id, $action, TRUE);
    if (!$warnings) {
      $this->logger->warning(dt('No available actions'));
      return;
    }

    // Use the last warning as the description.
    $warnings = $this->overrideManager->action($id, $action, TRUE);
    $description = array_pop($warnings);
    foreach ($warnings as $warning) {
      $warning = preg_replace("|</?em[^>]*>|", "'", $warning);
      $this->output()->writeln($warning);
    }
    if (!$this->io()->confirm(dt('!description Do you want to continue?', ['!description' => $description]))) {
      throw new UserAbortException();
    }

    $this->overrideManager->action($id, $action);
    $args = ['%name' => $info['name'], '%action' => $action_name];
    if ($id == OverrideManagerInterface::ALL_OVERRIDES) {
      $this->logger()->success(dt('Completed %action for all overrides', $args));
    }
    else {
      $this->logger()->success(dt('Completed %action for override %name', $args));
    }
  }

  /**
   * Gets information about Mailer overrides.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @command mailer:override-info
   * @field-labels
   *   name: Name
   *   state_name: State
   *   import: Import
   */
  public function overrideInfo(array $options = ['format' => 'table']): RowsOfFields {
    $info = $this->overrideManager->getInfo();
    foreach ($info as &$row) {
      if ($warning = $row['warning']) {
        $row['name'] .= "\nWarning: $warning";
      }
      if ($import_warning = $row['import_warning']) {
        $row['import'] .= "\nWarning: $import_warning";
      }
    }
    return new RowsOfFields($info);
  }

}
