<?php

declare(strict_types=1);

namespace Drupal\Tests\civicrm_entity\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\Tests\civicrm\FunctionalJavascript\CiviCrmTestBase;

/**
 * Base class for CiviCRM Entity tests.
 *
 * @method JSWebAssert assertSession(?string $name = NULL)
 */
abstract class CivicrmEntityTestBase extends CiviCrmTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'civicrm_entity',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Enable CiviCRM Entity types.
   *
   * @param array $entity_types
   *   The entity type Ids.
   */
  protected function enableCivicrmEntityTypes(array $entity_types): void {
    $this->drupalGet(Url::fromRoute('civicrm_entity.settings'));
    $page = $this->getSession()->getPage();
    foreach ($entity_types as $entity_type) {
      $page->checkField("enabled_entity_types[$entity_type][enabled]");
    }
    $this->pressButtonAndWait('Save configuration', 'The configuration options have been saved.');
  }

  /**
   * Presses a form button and waits for the page the submission loads.
   *
   * WebDriver can return from the click before the browser has loaded the
   * response. An assertion straight after it then reads the old page, and a
   * drupalGet() straight after it can abort the submission before it is
   * saved. Mark the current page, wait until a new one has replaced it, then
   * check the message the submission shows.
   *
   * @param string $button
   *   The button's id, name, value or label.
   * @param string $message
   *   Text the page shows once the submission has been processed.
   */
  protected function pressButtonAndWait(string $button, string $message): void {
    $session = $this->getSession();
    $session->executeScript('window.civicrmEntityBeforeSubmit = true;');
    $session->getPage()->pressButton($button);
    $loaded = $session->getPage()->waitFor(30, function () use ($session) {
      try {
        return $session->evaluateScript('typeof window.civicrmEntityBeforeSubmit === "undefined" && document.readyState === "complete"');
      }
      catch (\Exception $e) {
        // The old page can unload while the script runs. Try again.
        return FALSE;
      }
    });
    $this->assertTrue((bool) $loaded, "No new page loaded within 30 seconds of pressing \"$button\".");
    $this->assertSession()->pageTextContains($message);
  }

}
