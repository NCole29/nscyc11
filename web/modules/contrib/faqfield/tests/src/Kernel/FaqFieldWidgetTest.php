<?php

declare(strict_types=1);

namespace Drupal\Tests\faqfield\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\faqfield\Plugin\Field\FieldWidget\FaqFieldDefaultWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings of the FAQ field default widget.
 */
#[CoversClass(FaqFieldDefaultWidget::class)]
#[Group('faqfield')]
#[RunTestsInSeparateProcesses]
final class FaqFieldWidgetTest extends FaqFieldKernelTestBase {

  /**
   * Tests the answer widget setting picks the answer element type.
   */
  public function testAnswerWidgetSetting(): void {
    foreach (['textarea', 'textfield', 'text_format'] as $type) {
      $element = $this->buildElement(['answer_widget' => $type]);
      $this->assertSame($type, $element['answer']['#type']);
      // Only the formattable textarea carries a text format.
      $this->assertSame($type === 'text_format', isset($element['answer']['#format']), $type);
    }
  }

  /**
   * Tests the question becomes a textarea when rows are configured.
   */
  public function testQuestionRowsTurnQuestionIntoTextarea(): void {
    $element = $this->buildElement();
    $this->assertSame('textfield', $element['question']['#type']);

    $element = $this->buildElement(['advanced' => ['question_rows' => 2] + self::advancedDefaults()]);
    $this->assertSame('textarea', $element['question']['#type']);
    $this->assertSame(2, $element['question']['#rows']);
  }

  /**
   * Tests the required settings and limits reach the form elements.
   */
  public function testRequiredAndLimitSettings(): void {
    $element = $this->buildElement([
      'question_field_required' => TRUE,
      'answer_field_required' => TRUE,
      'advanced' => ['question_length' => 40, 'question_size' => 20, 'answer_rows' => 7] + self::advancedDefaults(),
    ]);
    $this->assertTrue($element['question']['#required']);
    $this->assertTrue($element['answer']['#required']);
    $this->assertSame(40, $element['question']['#maxlength']);
    $this->assertSame(20, $element['question']['#size']);
    $this->assertSame(7, $element['answer']['#rows']);
  }

  /**
   * Tests custom titles are escaped before they are used as labels.
   */
  public function testTitlesAreEscaped(): void {
    $element = $this->buildElement(['question_title' => '<script>Q</script>', 'answer_title' => 'A & B']);
    $this->assertSame('&lt;script&gt;Q&lt;/script&gt;', $element['question']['#title']);
    $this->assertSame('A &amp; B', $element['answer']['#title']);
  }

  /**
   * Tests the settings summary lists the widget and both titles.
   */
  public function testSettingsSummary(): void {
    $summary = array_map('strval', $this->widget(['answer_title' => 'Reply'])->settingsSummary());
    $expected = [
      'Answer widget : Formattable textarea',
      'Answer widget title: Reply',
      'Question widget title: Question',
    ];
    $this->assertSame($expected, $summary);
  }

  /**
   * Tests the settings form offers every widget setting.
   */
  public function testSettingsFormKeys(): void {
    $form = $this->widget()->settingsForm([], new FormState());
    $expected = [
      'answer_widget',
      'question_title',
      'answer_title',
      'question_field_required',
      'answer_field_required',
      'advanced',
    ];
    $this->assertSame($expected, array_keys($form));
    $expected_advanced = ['question_length', 'question_size', 'question_rows', 'answer_rows'];
    $this->assertSame($expected_advanced, Element::children($form['advanced']));
  }

  /**
   * Returns the settings of the "advanced" group at their defaults.
   *
   * @return array<string, int>
   *   Default advanced widget settings.
   */
  private static function advancedDefaults(): array {
    return FaqFieldDefaultWidget::defaultSettings()['advanced'];
  }

  /**
   * Creates the widget with some settings overridden.
   *
   * @param array<string, mixed> $settings
   *   Widget settings.
   */
  private function widget(array $settings = []): FaqFieldDefaultWidget {
    $entity = $this->createEntity([['question' => 'Q1', 'answer' => 'A1', 'answer_format' => 'raw']]);
    $widget = \Drupal::service('plugin.manager.field.widget')->getInstance([
      'field_definition' => $entity->getFieldDefinition('field_faq'),
      'form_mode' => 'default',
      'configuration' => ['type' => 'faqfield_default', 'settings' => $settings],
    ]);
    assert($widget instanceof FaqFieldDefaultWidget);
    return $widget;
  }

  /**
   * Builds the form element of the first item.
   *
   * @param array<string, mixed> $settings
   *   Widget settings.
   *
   * @return array<string, mixed>
   *   The form element.
   */
  private function buildElement(array $settings = []): array {
    $entity = $this->createEntity([['question' => 'Q1', 'answer' => 'A1', 'answer_format' => 'raw']]);
    $form = [];
    $form_state = new FormState();
    return $this->widget($settings)->formElement($entity->field_faq, 0, [], $form, $form_state);
  }

}
