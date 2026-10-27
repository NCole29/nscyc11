<?php

namespace Drupal\Tests\maxlength\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\editor\Entity\Editor;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Assert;

/**
 * Tests Javascript behavior of Maxlength module with CKEditor.
 *
 * @group maxlength
 */
class MaxLengthCkeditorTest extends WebDriverTestBase {

  /**
   * The user to use during testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'maxlength',
    'text',
    'ckeditor5',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => [],
    ])->save();
    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'weight' => 2,
      'filters' => [],
    ])->save();

    $this->user = $this->drupalCreateUser([
      'access administration pages',
      'administer entity_test content',
      'administer site configuration',
      'administer filters',
      'use text format full_html',
      'use text format basic_html',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the character count and limit works with CKEditor 5 version.
   */
  public function testCkeditor5() {
    Editor::create([
      'format' => 'full_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'heading',
            'bold',
            'italic',
            // Ensure we enable the source button for the test.
            'sourceEditing',
          ],
        ],
      ],
    ])->save();
    Editor::create([
      'format' => 'basic_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => [
            'heading',
            'bold',
            'italic',
            // Ensure we enable the source button for the test.
            'sourceEditing',
          ],
        ],
      ],
    ])->save();
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'entity_test',
      'field_name' => 'foo',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'foo',
      'label' => 'Foo',
      'description' => 'Description of a text field',
    ])->save();
    $widget = [
      'type' => 'text_textarea_with_summary',
      'settings' => [
        'show_summary' => TRUE,
        'summary_rows' => 3,
      ],
      'third_party_settings' => [
        'maxlength' => [
          'maxlength_js' => 200,
          'maxlength_js_label' => 'Content limited to @limit characters, remaining: <strong>@remaining</strong> and total @count',
        ],
      ],
    ];
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent('foo', $widget)
      ->save();

    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Test']);
    $entity->save();

    $page = $this->getSession()->getPage();
    $this->drupalLogin($this->user);
    $this->drupalGet($entity->toUrl('edit-form'));

    // Assert CKEditor5 is present.
    $settings = $this->getDrupalSettings();
    $this->assertContains('ckeditor5/internal.drupal.ckeditor5.emphasis', explode(',', $settings['ajaxPageState']['libraries']), 'CKEditor5 glue library is present.');

    // Assert the maxlength counter labels.
    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: 200 and total 0');

    // Give maxlength.js some time to manipulate the DOM.
    $this->assertSession()->waitForElement('css', 'div.counter');

    // Check that only a counter div is found on the page.
    $this->assertSession()->elementsCount('css', 'div.counter', 1);

    // Check that the counter div follows the description of the field.
    $found = $this->xpath('//div[@data-drupal-selector="edit-foo-0"]/following-sibling::div[@id="edit-foo-0-value-counter"]');
    $this->assertCount(1, $found);

    // Add some text to the field and assert the maxlength counters changed
    // accordingly.
    $this->enterTextInCkeditor5('Foo', 'Some text with <strong>html</strong>');

    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: 181 and total 19');

    // Fill the body field with more characters than the limit.
    $this->enterTextInCkeditor5('Foo', '<b>Lorem ipsum</b> dolor sit amet, <u>consectetur adipiscing</u> elit. Ut accumsan justo non interdum fermentum. Phasellus semper risus eu arcu eleifend dignissim. Class aptent taciti sociosqu ad litora erat curae. Extra characters');
    // The counter now should show "-17" for the extra characters.
    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: -17 and total 217');

    // Change the text format.
    $page->selectFieldOption('foo[0][format]', 'basic_html');
    $this->assertNotEmpty($this->assertSession()->waitForText('Change text format?'));
    $page->pressButton('Continue');
    $this->getSession()->wait(1000);

    // Add some text to the field and assert the maxlength counters changed
    // accordingly.
    $this->enterTextInCkeditor5('Foo', 'Some text with <strong>html</strong>');

    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: 181 and total 19');

    // Fill the body field with more characters than the limit.
    $this->enterTextInCkeditor5('Foo', '<b>Lorem ipsum</b> dolor sit amet, <u>consectetur adipiscing</u> elit. Ut accumsan justo non interdum fermentum. Phasellus semper risus eu arcu eleifend dignissim. Class aptent taciti sociosqu ad litora erat curae. Extra characters');
    // The counter now should show "-17" for the extra characters.
    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: -17 and total 217');

    // Now change the maxlength configuration to use "Hard limit".
    $widget['third_party_settings']['maxlength']['maxlength_js_enforce'] = TRUE;
    $display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('entity_test.entity_test.default');
    $display->setComponent('foo', $widget)->save();

    // Reload the page.
    $this->getSession()->reload();
    // Fill the body field with more characters than the limit.
    $this->enterTextInCkeditor5('Foo', '<b>Lorem ipsum</b> dolor sit amet, <br><u>consectetur adipiscing</u> elit. <img src=""><embed type="video/webm" src="">Ut accumsan justo non interdum fermentum. Phasellus semper risus eu arcu eleifend dignissim. Class aptent taciti sociosqu ad litora erat curae. Extra characterss');
    // Assert the "Extra characters" string is truncated.
    $this->assertSession()->pageTextContainsOnce('Content limited to 200 characters, remaining: 0 and total 200');
  }

  /**
   * Tests the hard limit does not move the cursor or drop trailing content.
   */
  public function testCkeditor5HardLimitCursorStability(): void {
    Editor::create([
      'format' => 'full_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => ['bold', 'italic'],
        ],
      ],
    ])->save();
    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'entity_test',
      'field_name' => 'foo',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'foo',
      'label' => 'Foo',
    ])->save();
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent('foo', [
        'type' => 'text_textarea',
        'third_party_settings' => [
          'maxlength' => [
            'maxlength_js' => 30,
            'maxlength_js_label' => 'Content limited to @limit characters, remaining: @remaining',
            'maxlength_js_enforce' => TRUE,
          ],
        ],
      ])
      ->save();

    $entity = EntityTest::create(['type' => 'entity_test', 'name' => 'Test']);
    $entity->save();

    $this->drupalGet($entity->toUrl('edit-form'));
    $this->assertSession()->waitForElement('css', '.ck-editor__editable');
    // Wait until maxlength has bound its enforcement to the editor.
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', 'textarea.maxlength[data-once~="maxlengthbinding"]'));
    $ckeditor5_id = $this->getCkeditor5Id($this->getCkeditor5('Foo'));

    // 28 characters, 2 below the limit of 30.
    $initial = 'abcdefghij-abcdefghij-abcdef';

    // Type past the limit at the end of the text. The keystrokes are sent
    // back to back, like when a user keeps typing after the limit is reached.
    $this->setCkeditor5TextAndCursor($ckeditor5_id, $initial, 28);
    $this->typeInCkeditor5($ckeditor5_id, '123456');
    $state = $this->getCkeditor5State($ckeditor5_id);
    // The two characters still within the limit are kept, the rest is
    // rejected and no pre-existing characters are lost.
    $this->assertSame('abcdefghij-abcdefghij-abcdef12', $state['text']);
    // The cursor stays at the typing position instead of jumping to the
    // beginning of the document.
    $this->assertSame([0, 30], $state['cursor']);

    // Type past the limit in the middle of the text.
    $this->setCkeditor5TextAndCursor($ckeditor5_id, $initial, 10);
    $this->typeInCkeditor5($ckeditor5_id, 'XYZWVU');
    $state = $this->getCkeditor5State($ckeditor5_id);
    $this->assertSame('abcdefghijXY-abcdefghij-abcdef', $state['text']);
    $this->assertSame([0, 12], $state['cursor']);

    // Paste a chunk in the middle of the text in a single insertion. The
    // pasted chunk itself is trimmed and the content after the cursor is
    // preserved.
    $this->setCkeditor5TextAndCursor($ckeditor5_id, $initial, 10);
    $this->typeInCkeditor5($ckeditor5_id, '0123456789', TRUE);
    $state = $this->getCkeditor5State($ckeditor5_id);
    $this->assertSame('abcdefghij01-abcdefghij-abcdef', $state['text']);
    $this->assertSame([0, 12], $state['cursor']);
  }

  /**
   * Sets the CKEditor 5 content to a paragraph and places the cursor in it.
   *
   * @param string|int $ckeditor5_id
   *   The CKEditor 5 instance ID.
   * @param string $text
   *   The plain text content of the paragraph.
   * @param int $offset
   *   The cursor offset inside the paragraph.
   */
  protected function setCkeditor5TextAndCursor(string|int $ckeditor5_id, string $text, int $offset): void {
    $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get('$ckeditor5_id');
  editor.setData(`<p>$text</p>`);
  editor.editing.view.focus();
  editor.model.change((writer) => {
    const paragraph = editor.model.document.getRoot().getChild(0);
    writer.setSelection(writer.createPositionAt(paragraph, $offset));
  });
})();
JS;
    $this->getSession()->evaluateScript($javascript);
  }

  /**
   * Types text into CKEditor 5 at the current cursor position.
   *
   * @param string|int $ckeditor5_id
   *   The CKEditor 5 instance ID.
   * @param string $text
   *   The text to type.
   * @param bool $single_insertion
   *   When TRUE the whole text is inserted at once, like a paste. Otherwise
   *   each character is inserted separately, like typing.
   */
  protected function typeInCkeditor5(string|int $ckeditor5_id, string $text, bool $single_insertion = FALSE): void {
    $chunks_js = $single_insertion ? "[`$text`]" : "`$text`.split('')";
    $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get('$ckeditor5_id');
  $chunks_js.forEach((chunk) => {
    editor.execute('insertText', { text: chunk });
  });
})();
JS;
    $this->getSession()->evaluateScript($javascript);
  }

  /**
   * Returns the CKEditor 5 plain text content and cursor position.
   *
   * @param string|int $ckeditor5_id
   *   The CKEditor 5 instance ID.
   *
   * @return array
   *   An array with a "text" key holding the tag-less editor content and a
   *   "cursor" key holding the model path of the selection start.
   */
  protected function getCkeditor5State(string|int $ckeditor5_id): array {
    $javascript = <<<JS
(function(){
  const editor = Drupal.CKEditor5Instances.get('$ckeditor5_id');
  return JSON.stringify({
    text: editor.getData().replace(/<[^>]*>/g, ''),
    cursor: editor.model.document.selection.getFirstPosition().path,
  });
})();
JS;
    return json_decode($this->getSession()->evaluateScript($javascript), TRUE);
  }

  /**
   * Enters the given text in the textarea of the specified CKEditor 5.
   *
   * If there is any text existing it will be replaced.
   *
   * @param string $field
   *   The label of the field to which the CKEditor is attached. For example
   *   'Body'.
   * @param string $text
   *   The text to enter in the textarea.
   */
  protected function setCkeditor5Text(string $field, string $text): void {
    $wysiwyg = $this->getCkeditor5($field);
    $ckeditor5_id = $this->getCkeditor5Id($wysiwyg);
    $javascript = <<<JS
(function(){
  Drupal.CKEditor5Instances.get('$ckeditor5_id').setData(`$text`);
  // Add temporary mechanism to update the source element.
  // @see https://www.drupal.org/i/2722319
  const editor = Drupal.CKEditor5Instances.get('$ckeditor5_id');
  if (editor) {
    jQuery(once('ckeditor5-states-binding', editor.sourceElement)).each(
      function () {
        editor.model.document.on('change', function () {
          if (editor.getData() !== editor.sourceElement.textContent) {
            editor.updateSourceElement();
            jQuery(editor.sourceElement).trigger('change', [true]);
          }
        });
      },
    );
  }
})();
JS;
    $this->getSession()->evaluateScript($javascript);
    $wysiwyg->click();
  }

  /**
   * Returns the CKEditor 5 that is associated with the given field label.
   *
   * @param string $field
   *   The label of the field to which the CKEditor is attached.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The WYSIWYG editor.
   */
  protected function getCkeditor5(string $field): NodeElement {
    $driver = $this->getSession()->getDriver();
    $label_elements = $driver->find('//label[text()="' . $field . '"]');
    Assert::assertNotEmpty($label_elements, "Could not find the '$field' field label.");
    Assert::assertCount(1, $label_elements, "Multiple '$field' labels found in the page.");

    $wysiwyg_elements = $driver->find('//label[contains(text(), "' . $field . '")]/following::div[contains(@class, " ck-editor ")][1]');
    Assert::assertNotEmpty($wysiwyg_elements, "Could not find the '$field' wysiwyg editor.");
    Assert::assertCount(1, $wysiwyg_elements, "Multiple '$field' wysiwyg editors found in the page.");

    return reset($wysiwyg_elements);
  }

  /**
   * Enters the given text in the given CKEditor 5.
   *
   * @param string $label
   *   The label of the field containing the CKEditor.
   * @param string $text
   *   The text to enter in the CKEditor.
   */
  protected function enterTextInCkeditor5(string $label, string $text): void {
    $this->setCkeditor5Text($label, $text);
  }

  /**
   * Gets the "data-ckeditor5-id" attribute value.
   *
   * @param \Behat\Mink\Element\NodeElement $wysiwyg
   *   The WYSIWYG element.
   *
   * @return string|int
   *   Returns the "data-ckeditor5-id" attribute value.
   */
  protected function getCkeditor5Id(NodeElement $wysiwyg): string|int {
    $textarea = $this->getSession()->getDriver()->find($wysiwyg->getXpath() . '/preceding-sibling::textarea');
    Assert::assertNotEmpty($textarea, "Could not find the textarea element.");

    $textarea = reset($textarea);
    $ckeditor_id = $textarea->getAttribute('data-ckeditor5-id');
    Assert::assertNotEmpty($ckeditor_id, "Could not find the textarea element's ckeditor5 id.");

    return $ckeditor_id;
  }

}
