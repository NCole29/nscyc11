<?php

declare(strict_types=1);

namespace Drupal\Tests\faqfield\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\faqfield\Plugin\Field\FieldType\FaqFieldItem;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the faqfield field type, its formatters and its widget.
 */
#[CoversClass(FaqFieldItem::class)]
#[Group('faqfield')]
#[RunTestsInSeparateProcesses]
final class FaqFieldTest extends FaqFieldKernelTestBase {

  /**
   * Tests the default cardinality is unlimited.
   */
  public function testCardinalityUnlimited(): void {
    $storage = FieldStorageConfig::loadByName('entity_test', 'field_faq');
    $this->assertSame(FieldStorageConfig::CARDINALITY_UNLIMITED, $storage->getCardinality());
  }

  /**
   * Tests that values survive a save and load round trip.
   */
  public function testStorageRoundTrip(): void {
    $entity = $this->createEntity([
      ['question' => 'Q1', 'answer' => 'A1', 'answer_format' => 'raw'],
      ['question' => 'Q2', 'answer' => 'A2', 'answer_format' => NULL],
    ]);
    $entity = EntityTest::load($entity->id());
    $this->assertCount(2, $entity->field_faq);
    $this->assertSame('Q1', $entity->field_faq[0]->question);
    $this->assertSame('A1', $entity->field_faq[0]->answer);
    $this->assertSame('raw', $entity->field_faq[0]->answer_format);
    $this->assertNull($entity->field_faq[1]->answer_format);
  }

  /**
   * Tests that an answer submitted by a text_format element is split up.
   */
  public function testSetValueSplitsTextFormatAnswer(): void {
    $entity = $this->createEntity([
      ['question' => 'Q', 'answer' => ['value' => 'A', 'format' => 'raw']],
    ]);
    $this->assertSame('A', $entity->field_faq[0]->answer);
    $this->assertSame('raw', $entity->field_faq[0]->answer_format);
  }

  /**
   * Tests that a text_format answer without a format does not raise errors.
   */
  public function testSetValueAnswerArrayWithoutFormat(): void {
    $entity = $this->createEntity([
      ['question' => 'Q', 'answer' => ['value' => 'A']],
    ]);
    $this->assertSame('A', $entity->field_faq[0]->answer);
    $this->assertNull($entity->field_faq[0]->answer_format);
  }

  /**
   * Tests isEmpty().
   *
   * @param array<string, mixed> $values
   *   The item values.
   * @param bool $expected
   *   Whether the item is expected to be empty.
   */
  #[DataProvider('providerIsEmpty')]
  public function testIsEmpty(array $values, bool $expected): void {
    $entity = EntityTest::create(['field_faq' => [$values]]);
    $this->assertSame($expected, $entity->field_faq[0]->isEmpty());
  }

  /**
   * Data provider for testIsEmpty().
   *
   * @return array<string, array{array<string, mixed>, bool}>
   *   Item values and expected emptiness.
   */
  public static function providerIsEmpty(): array {
    return [
      'nothing' => [[], TRUE],
      'empty strings' => [['question' => '', 'answer' => ''], TRUE],
      'format only' => [['answer_format' => 'raw'], TRUE],
      'question only' => [['question' => 'Q'], FALSE],
      'answer only' => [['answer' => 'A'], FALSE],
      'text_format answer' => [['answer' => ['value' => 'A', 'format' => 'raw']], FALSE],
      'empty text_format answer' => [['answer' => ['value' => '', 'format' => 'raw']], TRUE],
    ];
  }

  /**
   * Tests that empty items are dropped when the field list is filtered.
   */
  public function testEmptyItemsAreFiltered(): void {
    $entity = EntityTest::create([
      'field_faq' => [
        ['question' => 'Q', 'answer' => 'A'],
        ['question' => '', 'answer' => ''],
      ],
    ]);
    $entity->field_faq->filterEmptyItems();
    $this->assertCount(1, $entity->field_faq);
  }

  /**
   * Tests the field type definition.
   */
  public function testFieldDefinition(): void {
    $this->assertSame('question', FaqFieldItem::mainPropertyName());
    $this->assertSame(['default_format' => 'plain_text'], FaqFieldItem::defaultFieldSettings());
    $schema = FaqFieldItem::schema(FieldStorageConfig::loadByName('entity_test', 'field_faq'));
    $this->assertSame(['question', 'answer', 'answer_format'], array_keys($schema['columns']));
    $this->assertSame(255, $schema['columns']['question']['length']);
    $this->assertSame('medium', $schema['columns']['answer']['size']);
  }

  /**
   * Tests the simple text formatter.
   */
  public function testSimpleTextFormatter(): void {
    $entity = $this->createEntity([
      ['question' => '<b>Q1</b>', 'answer' => '<i>A1</i>', 'answer_format' => 'raw'],
      ['question' => 'Q2', 'answer' => '<i>A2</i>'],
    ]);
    $html = $this->renderField($entity, 'faqfield_simple_text', ['question_tag' => 'h4']);
    $this->assertStringContainsString('<h4 class="faqfield-question">Q1</h4>', $html);
    $this->assertStringContainsString('<div class="faqfield-answer"><i>A1</i></div>', $html);
    // Items without a format use the field's default format.
    $this->assertStringContainsString('<div class="faqfield-answer"><p>&lt;i&gt;A2&lt;/i&gt;</p></div>', $html);
  }

  /**
   * Tests the definition list formatter.
   */
  public function testDefinitionListFormatter(): void {
    $entity = $this->createEntity([
      ['question' => 'Q1', 'answer' => 'A1', 'answer_format' => 'raw'],
      ['question' => 'Q2', 'answer' => 'A2', 'answer_format' => 'raw'],
    ]);
    $html = $this->renderField($entity, 'faqfield_definition_list');
    $this->assertSame(1, substr_count($html, '<dl class="faqfield-definition-list">'));
    $this->assertSame(2, substr_count($html, '<dt class="faqfield-question">'));
    $this->assertStringContainsString('<dd class="faqfield-answer">A2</dd>', $html);
  }

  /**
   * Tests the details formatter.
   */
  public function testDetailsFormatter(): void {
    $entity = $this->createEntity([
      ['question' => 'Q1', 'answer' => 'A1', 'answer_format' => 'raw'],
    ]);
    $html = $this->renderField($entity, 'faqfield_details');
    $this->assertStringContainsString('<summary class="faqfield-question">Q1</summary>', $html);
    $this->assertStringContainsString('<div class="faqfield-answer">A1</div>', $html);
  }

  /**
   * Tests the anchor list formatter.
   */
  public function testAnchorListFormatter(): void {
    $entity = $this->createEntity([
      ['question' => 'Q 1', 'answer' => 'A1', 'answer_format' => 'raw'],
    ]);
    $html = $this->renderField($entity, 'faqfield_anchor_list', ['anchor_list_type' => 'ol']);
    $this->assertStringContainsString('<ol', $html);
    $this->assertStringContainsString('name="faq-Q-1"', $html);
    $this->assertStringContainsString('A1', $html);
  }

  /**
   * Tests the accordion formatter markup and attached settings.
   */
  public function testAccordionFormatter(): void {
    $entity = $this->createEntity([
      ['question' => 'Q1', 'answer' => 'A1', 'answer_format' => 'raw'],
    ]);
    $build = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'status' => TRUE,
    ])->setComponent('field_faq', ['type' => 'faqfield_accordion', 'settings' => ['active' => '']])
      ->build($entity);
    $id = 'faqfield_field_faq_entity_test_' . $entity->id();
    $field = $build['field_faq'];
    $this->assertSame($id, $field[0]['#id']);
    $this->assertContains('faqfield/faqfield.accordion', $field[0]['#attached']['library']);
    // A blank "active" setting means no panel is open by default.
    $this->assertFalse($field[0]['#attached']['drupalSettings']['faqfield']['#' . $id]['active']);
    $html = preg_replace('/>\s+</', '><', (string) \Drupal::service('renderer')->renderInIsolation($field));
    $this->assertStringContainsString('<div id="' . $id . '">', $html);
    $this->assertStringContainsString('<h3 class="faqfield-question">Q1</h3>', $html);
  }

  /**
   * Tests that formatters render nothing for an empty field.
   */
  public function testFormattersRenderNothingWhenEmpty(): void {
    $entity = EntityTest::create();
    $entity->save();
    foreach (['faqfield_definition_list', 'faqfield_anchor_list', 'faqfield_accordion'] as $type) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'status' => TRUE,
      ])->setComponent('field_faq', ['type' => $type]);
      $build = $display->build($entity);
      $this->assertEmpty(Element::children($build['field_faq']), $type);
    }
  }

  /**
   * Tests the default widget form element.
   */
  public function testWidgetFormElement(): void {
    $entity = $this->createEntity([
      ['question' => 'Q1', 'answer' => 'A1', 'answer_format' => 'raw'],
    ]);
    $form = [];
    $form_state = new FormState();
    $widget = \Drupal::service('plugin.manager.field.widget')->getInstance([
      'field_definition' => $entity->getFieldDefinition('field_faq'),
      'form_mode' => 'default',
      'configuration' => ['type' => 'faqfield_default', 'settings' => []],
    ]);
    $element = $widget->formElement($entity->field_faq, 0, [], $form, $form_state);
    $this->assertSame('textfield', $element['question']['#type']);
    $this->assertSame('Q1', $element['question']['#default_value']);
    $this->assertSame(255, $element['question']['#maxlength']);
    $this->assertSame('text_format', $element['answer']['#type']);
    $this->assertSame('A1', $element['answer']['#default_value']);
    $this->assertSame('raw', $element['answer']['#format']);
    // A new item falls back to the field's default format.
    $element = $widget->formElement($entity->field_faq, 1, [], $form, $form_state);
    $this->assertSame('plain_text', $element['answer']['#format']);
  }

}
