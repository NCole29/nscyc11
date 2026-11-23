<?php

declare(strict_types=1);

namespace Drupal\Tests\civicrm_entity\FunctionalJavascript\Views;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\Tests\civicrm_entity\FunctionalJavascript\CivicrmEntityTestBase;

/**
 * Test of Full Calendar Activity Views.
 *
 * @requires module fullcalendar_view
 */
final class ActivityFullcalendarViewTest extends CivicrmEntityTestBase {

  /**
   * {@inheritdoc}
   *
   * @todo fix config schema from this module for Views.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The test activity.
   *
   * @var mixed
   */
  private $activityId;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   *
   * @todo needs more data.
   */
  public function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['node']);
    $this->container->get('module_installer')->install(['civicrm_entity_fullcalendar_test']);

    $admin_user = $this->createUser([
      'access content',
      'administer civicrm entity',
    ]);
    $this->drupalLogin($admin_user);
    $this->enableCivicrmEntityTypes(['civicrm_activity', 'civicrm_contact']);

    $civicrm_api = $this->container->get('civicrm_entity.api');
    $result = $civicrm_api->save('Contact', [
      'contact_type' => 'Individual',
      'first_name' => 'Johnny',
      'last_name' => 'Appleseed',
      'email' => 'johnny@example.com',
    ]);
    $contact_id = $result['id'];
    $user = $this->createUser([], 'johnny');

    // Verify the user and contact linked.
    $fetched_contact_id = \CRM_Core_BAO_UFMatch::getContactId($user->id());
    self::assertEquals($contact_id, $fetched_contact_id);

    $date = new DrupalDateTime('now');
    $result = $civicrm_api->save('Activity', [
      'source_contact_id' => $contact_id,
      'activity_type_id' => 'Meeting',
      'subject' => 'Meeting about new seeds',
      'activity_date_time' => $date->format('Y-m-d') . ' 12:00:00',
      'status_id' => 2,
      'priority_id' => 1,
      'duration' => 120,
      'location' => 'Pennsylvania',
      'details' => 'We need to find more seeds!',
    ]);
    $this->activityId = $result['id'];

    \Drupal::configFactory()
      ->getEditable('views.settings')
      ->set('ui.always_live_preview', FALSE)
      ->set('ui.show.advanced_column', TRUE)
      ->save();
  }

  /**
   * Test Full Calendar Display.
   */
  public function testFullcalendarDisplay(): void {
    $this->drupalGet('/activity-fullcalendar');
    $this->createScreenshot('../calendar.png');
    $fullcalendar = $this->assertSession()->elementExists('css', '.js-drupal-fullcalendar');
    $this->assertSession()->waitForElement('css', '.fc-event-container');
    $this->assertSession()->elementTextContains('css', '.fc-event-container .fc-time', '12:00 pm');
    $this->assertSession()->elementTextContains('css', '.fc-event-container .fc-title', 'Meeting about new seeds');

    // @todo The dialog opened is extremely difficult to target.
    $this->click('.fc-event-container .fc-event');
    $this->assertSession()->waitForElement('css', '.jsframe-titlebar-focused');
    $this->assertSession()->elementTextContains('css', '.jsframe-titlebar-focused', 'Meeting about new seeds');

    $modal = $this->assertSession()->elementExists('css', 'div[id^="window_"][id$="_canvas"]');
    self::assertNotNull($modal);
    $this->assertSession()->elementTextContains('css', 'div[id^="window_"][id$="_canvas"]', 'Meeting about new seeds');
    $this->assertSession()->elementTextContains('css', 'div[id^="window_"][id$="_canvas"]', 'johnny');

    $civicrm_api = $this->container->get('civicrm_entity.api');
    $activity = $civicrm_api->get('activity', [
      'id' => $this->activityId,
    ]);

    $date = new DrupalDateTime($activity[$this->activityId]['activity_date_time']);
    $duration = $activity[$this->activityId]['duration'];
    $start_formatted = $date->format('D, m/d/Y - H:i');
    $date->add(new \DateInterval("PT{$duration}M"));
    $end_formatted = $date->format('D, m/d/Y - H:i');
    $this->assertSession()->pageTextContains($start_formatted . ' - ' . $end_formatted);
  }

  /**
   * Test Activity Drag and Update.
   */
  public function testActivityDragAndUpdate(): void {
    $previous_day = new DrupalDateTime('-1 day');
    $previous_day_formatted = $previous_day->format('Y-m-d');

    $this->drupalGet('/activity-fullcalendar');
    $fullcalendar = $this->assertSession()->elementExists('css', '.js-drupal-fullcalendar');
    $event = $this->assertSession()->waitForElement('css', '.fc-event-container .fc-event');
    $destination = $this->assertSession()->elementExists('css', ".fc-bg [data-date='$previous_day_formatted']", $fullcalendar);

    // The calendar asks to confirm the drop with window.confirm(), and reports
    // a failed save with window.alert(). A native dialog can open while the
    // WebDriver command that caused it is still running, or after the test
    // has already looked for it, depending on timing. One still open at the
    // end of the test also breaks tearDown, which then leaves CiviCRM's
    // tables behind and fails every later test. Record the dialogs in the
    // page instead, as fullcalendar_view's own tests do.
    $this->getSession()->executeScript(<<<'JS'
      window.civicrmEntityDialogs = {confirm: [], alert: []};
      window.confirm = function (message) {
        window.civicrmEntityDialogs.confirm.push(String(message));
        return true;
      };
      window.alert = function (message) {
        window.civicrmEntityDialogs.alert.push(String(message));
      };
    JS);

    $driver = $this->getSession()->getDriver();
    assert($driver instanceof DrupalSelenium2Driver);
    $webdriver_session = $driver->getWebDriverSession();

    $webdriver_session->moveto([
      'element' => $webdriver_session->element('xpath', $event->getXpath())->getID(),
    ]);
    $webdriver_session->buttondown();
    $webdriver_session->moveto([
      'element' => $webdriver_session->element('xpath', $destination->getXpath())->getID(),
    ]);
    $webdriver_session->buttonup();

    $this->assertJsCondition('window.civicrmEntityDialogs.confirm.length > 0', 10000, 'The calendar did not ask to confirm the drop.');
    $dialogs = $this->getSession()->evaluateScript('window.civicrmEntityDialogs');
    $this->assertCount(1, $dialogs['confirm']);
    $this->assertMatchesRegularExpression(
      '/^Meeting about new seeds start is now ([0-9]{4})-(1[0-2]|0[1-9])-(3[01]|0[1-9]|[12][0-9]) (2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9]) and end is now ([0-9]{4})-(1[0-2]|0[1-9])-(3[01]|0[1-9]|[12][0-9]) (2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9]) - Do you want to save this change\?$/',
      $dialogs['confirm'][0]
    );
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSame([], $this->getSession()->evaluateScript('window.civicrmEntityDialogs.alert'), 'The calendar reported an error saving the change.');

    $civicrm_api = $this->container->get('civicrm_entity.api');
    $activity = $civicrm_api->get('activity', [
      'id' => $this->activityId,
    ]);
    $this->assertEquals($previous_day_formatted . ' 12:00:00', $activity[$this->activityId]['activity_date_time']);
  }

}
