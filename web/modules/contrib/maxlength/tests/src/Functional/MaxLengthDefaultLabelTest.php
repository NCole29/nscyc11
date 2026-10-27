<?php

namespace Drupal\Tests\maxlength\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the default count down message is not persisted in config.
 *
 * @group maxlength
 */
class MaxLengthDefaultLabelTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'maxlength',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The name of the test field.
   *
   * @var string
   */
  protected $fieldName = 'field_maxlength_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'text_long',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'MaxLength test field',
    ])->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent($this->fieldName, [
        'type' => 'text_textarea',
      ])
      ->save();
  }

  /**
   * Tests the count down message stays out of config when left empty.
   */
  public function testDefaultLabelIsNotPersisted(): void {
    $default_label = 'Content limited to @limit characters, remaining: <strong>@remaining</strong>';

    $admin_user = $this->drupalCreateUser([
      'administer node form display',
      'create article content',
    ]);
    $this->drupalLogin($admin_user);

    // Open the widget settings and enable maxlength without entering a
    // custom count down message.
    $this->drupalGet('admin/structure/types/manage/article/form-display');
    $page = $this->getSession()->getPage();
    $page->pressButton($this->fieldName . '_settings_edit');

    // The message field is empty and the default is offered as placeholder.
    // Placeholder attributes are rendered as plain text, so the markup is
    // stripped from it.
    $label_field = $this->assertSession()->fieldExists('Count down message');
    $this->assertSame('', $label_field->getValue());
    $this->assertSame('Content limited to @limit characters, remaining: @remaining', $label_field->getAttribute('placeholder'));

    $page->fillField('Maximum length', '100');
    $page->pressButton('Update');
    $page->pressButton('Save');

    // The maximum length is stored, but no count down message is written to
    // the config.
    $display = EntityFormDisplay::load('node.article.default');
    $settings = $display->getComponent($this->fieldName)['third_party_settings']['maxlength'];
    $this->assertEquals(100, $settings['maxlength_js']);
    $this->assertArrayHasKey('maxlength_js_label', $settings);
    $this->assertSame('', (string) $settings['maxlength_js_label']);
    $this->assertStringNotContainsString('Content limited to', serialize($display->toArray()));

    // The default message is still applied to the widget at render time.
    $this->drupalGet('node/add/article');
    $textarea = $this->assertSession()->fieldExists('MaxLength test field');
    $this->assertSame('100', $textarea->getAttribute('data-maxlength'));
    $this->assertSame($default_label, $textarea->getAttribute('maxlength_js_label'));
  }

}
