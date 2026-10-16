<?php

namespace Drupal\Tests\views_row_sdc\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views\Entity\View;

/**
 * Test settings form updates when component selected.
 */
#[Group('views_row_sdc')]
#[RunTestsInSeparateProcesses]
class RowSettingsUpdateTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'views_ui',
    'views_row_sdc',
    'views_row_sdc_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Set up test with basic view and admin user.
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content type + content if needed.
    $this->drupalCreateContentType(['type' => 'article']);

    // Create a basic view programmatically.
    View::create([
      'id' => 'test_view',
      'label' => 'Test View',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'node_field_data',
                'field' => 'title',
              ],
            ],
            'access' => ['type' => 'none'],
            'query' => ['type' => 'views_query'],
            'row' => [
              'type' => 'fields',
            ],
          ],
        ],
      ],
    ])->save();

    // Login admin.
    $this->drupalLogin($this->drupalCreateUser([
      'administer views',
    ]));
  }

  /**
   * Utility function to wait for element to appear.
   */
  protected function waitForAppearance($type, $selector) {
    $page = $this->getSession()->getPage();
    return $page->waitFor(10,
      function () use ($type, $selector, $page) {
        return $page->find($type, $selector);
      }
    );
  }

  /**
   * Test setting row style to SDC and selecting component shows props/slots.
   */
  public function testAjaxUpdatesPropsAndSlots(): void {
    // Open Views form.
    $this->drupalGet('/admin/structure/views/view/test_view/edit');
    $session = $this->getSession();
    $page = $session->getPage();
    // Open row style popup.
    $page->clickLink('Change the way each row in the view is styled.');
    // $session->wait(100);
    $this->waitForAppearance('xpath', "//label[normalize-space()='Single Directory Component']");
    // Switch to SDC plugin.
    $radio = $page->findField('Single Directory Component');
    $this->assertNotEmpty($radio);
    $radio->click();
    // Get the dialog button pane, and press the Apply button.
    $dialog_buttons = $page->find('css', '.ui-dialog-buttonset');
    $dialog_buttons->pressButton('Apply');

    // Wait for AJAX callback (::updateSdcProps).
    $this->waitForAppearance('css', "#sdc-wrapper");

    // Now assert props + slots exist.
    $props = $page->find('xpath', "//details[.//summary[contains(normalize-space(.), 'Props')]]");
    $this->assertEmpty($props);

    // Wait for AJAX.
    $this->waitForAppearance('xpath', "//select[@data-drupal-selector='edit-row-options-component-id']");

    // Select a component.
    $page->selectFieldOption(
      'Component',
      'views_row_sdc_test:test_card',
    );

    // Wait for SDC wrapper to display.
    $this->waitForAppearance('css', "#sdc-wrapper");

    // Now assert props + slots exist.
    $props = $this->waitForAppearance('xpath', "//details[.//summary[contains(normalize-space(.), 'Props')]]");
    $this->assertNotEmpty($props);

    // Example: check a prop field exists.
    $this->assertSession()->fieldExists('Title');
    $this->assertSession()->fieldExists('Subtitle');

    // Example: check a slot field exists.
    $this->assertSession()->fieldExists('Media');
  }

  /**
   * Test that prop and slop mappings save correctly.
   */
  public function testSavePropsAndSlotMappings(): void {
    // Open Views form.
    $this->drupalGet('/admin/structure/views/view/test_view/edit');
    $session = $this->getSession();
    $page = $session->getPage();
    // Open row style popup.
    $page->clickLink('Change the way each row in the view is styled.');
    // $session->wait(100);
    $this->waitForAppearance('xpath', "//label[normalize-space()='Single Directory Component']");
    // Switch to SDC plugin.
    $radio = $page->findField('Single Directory Component');
    $this->assertNotEmpty($radio);
    $radio->click();
    // Get the dialog button pane, and press the Apply button.
    $dialog_buttons = $page->find('css', '.ui-dialog-buttonset');
    $dialog_buttons->pressButton('Apply');

    // Wait for AJAX.
    $this->waitForAppearance('xpath', "//select[@data-drupal-selector='edit-row-options-component-id']");

    // Select a component.
    $page->selectFieldOption(
      'Component',
      'views_row_sdc_test:test_card',
    );

    // Now assert props + slots exist.
    $this->waitForAppearance('xpath', "//details[.//summary[contains(normalize-space(.), 'Props')]]");
    $page->selectFieldOption(
      'Title',
      'Content: Title',
    );
    $page->selectFieldOption(
      'Media',
      'Content: Title',
    );

    // Get the dialog button pane, and press the Apply button.
    $dialog_buttons = $page->find('css', '.ui-dialog-buttonset');
    $dialog_buttons->pressButton('Apply');

    // Save the view.
    $this->submitForm([], 'Save');

    // Check for confirmation form has saved.
    $this->waitForAppearance('css', '.messages');
    $page->hasContent('The view Card test has been saved.');

    // Load the view configuration.
    $view = View::load('test_view');

    // Make sure view row style is SDC.
    $display = $view->getDisplay('default');
    $row = $display['display_options']['row'];
    $this->assertEquals('sdc_component', $row['type']);

    // Make sure test card is selected component.
    $options = $row['options'];
    $this->assertEquals('views_row_sdc_test:test_card', $options['component_id']);

    // Confirm prop and slot mappings stored correctly.
    $this->assertEquals('title', $options['props']['title']['field']);
    $this->assertEquals('string', $options['props']['title']['type']);
    $this->assertNotContains('subtitle', $options['props']);
    $this->assertEquals('title', $options['slots']['media']['field']);
  }

}
