<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Guards the Solo theme settings form end to end.
 *
 * WHY THIS EXISTS
 * Solo builds its entire settings UI from hook_form_system_theme_settings_alter
 * by require_once'ing 23 include files INTO that function's scope. The includes
 * read $form, $form_state, $attributes, $updated_regions and $counts from the
 * caller; none of them stands alone.
 *
 * That arrangement has to survive two things:
 *
 *   1. Drupal 11.5.0 deprecated shipping a theme-settings.php file at all, and
 *      core says it is removed in 12.0.0. Core triggers on the FILE EXISTING,
 *      not on its contents, so clearing it means moving the hook out and
 *      deleting the file.
 *   2. On Drupal 12 core only scans "<theme>.theme" and "theme-settings.php"
 *      for PROCEDURAL theme hooks. A hook moved into includes/ is invisible to
 *      that scan, so the same hook must also exist as a #[Hook] class method or
 *      the settings form silently loses every Solo section while still
 *      returning HTTP 200.
 *
 * Point 2 is why this test asserts on CONTENT and on a round-trip SAVE rather
 * than on the status code: an empty-but-successful settings page is exactly the
 * failure mode being guarded against.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
#[RunTestsInSeparateProcesses]
class SoloThemeSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'solo';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'block', 'user'];

  /**
   * The settings route for this theme.
   */
  private const SETTINGS_PATH = '/admin/appearance/settings/solo';

  /**
   * The form builds, and it carries Solo's own sections.
   */
  public function testSettingsFormBuildsWithSoloSections(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer themes']));
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertSession()->statusCodeEquals(200);

    // Solo's hook builds the overwhelming majority of this form: 356 named
    // fields with it, barely a dozen without. A count is a far better guard
    // than any single field name, because the failure being caught is "the
    // hook stopped running and the page still returned 200".
    //
    // Deliberately NOT asserting a core field such as default_logo: core gates
    // its logo and favicon sections on the File module
    // (ThemeSettingsForm.php:232, "only available when file.module is
    // enabled"), so those names come and go with the site's module list and
    // would make this test environment-dependent.
    $html = $this->getSession()->getPage()->getContent();
    preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"/i', $html, $matches);
    $fieldCount = count(array_unique($matches[1]));
    $this->assertGreaterThan(100, $fieldCount, sprintf(
      'Only %d named fields rendered. Solo builds this form from 23 includes '
      . 'pulled into hook_form_system_theme_settings_alter; a collapse to a '
      . 'handful means the hook is no longer running.',
      $fieldCount
    ));

    // Solo's sections. These come from the includes that are require_once'd
    // into the hook's scope - if the hook stops running, the page still
    // returns 200 and these disappear.
    $this->assertSession()->fieldExists('theme_category');
    $this->assertSession()->fieldExists('predefined_current_theme');
    // Deliberately NOT asserting the section's visible title. That #title is
    // built from markup ("<strong class='warning'>Predefined Color
    // Schemes</strong> Settings - Solo Theme"), so the phrase is present in
    // the raw HTML but not in the tag-stripped page text Mink compares
    // against. The two fields above prove the same section rendered.
  }

  /**
   * A Solo setting round-trips through validate and submit.
   *
   * This is the assertion that the #[Hook] class, the validate handler and the
   * submit handler are all wired, not merely that markup rendered.
   */
  public function testSoloSettingSavesAndPersists(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer themes']));
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm(['theme_category' => 'none'], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    $saved = \Drupal::config('solo.settings')->get('theme_category');
    $this->assertSame(
      'none',
      $saved,
      'The Solo setting did not persist, so the submit path is not wired.'
    );
  }

  /**
   * The form's own stylesheet is attached, proving the hook body ran.
   */
  public function testSettingsFormLibraryIsAttached(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer themes']));
    $this->drupalGet(self::SETTINGS_PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('solo-form-theme-settings.css');
  }

}
