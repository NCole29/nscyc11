<?php

declare(strict_types=1);

namespace Drupal\Tests\faqfield\Kernel;

use Drupal\faqfield\Plugin\Field\FieldFormatter\FaqFieldAnchorListFormatter;
use Drupal\filter\Entity\FilterFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that every formatter keeps stored markup from reaching the page.
 */
#[CoversClass(FaqFieldAnchorListFormatter::class)]
#[Group('faqfield')]
#[RunTestsInSeparateProcesses]
final class FaqFieldOutputTest extends FaqFieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    FilterFormat::create([
      'format' => 'limited',
      'name' => 'Limited',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => ['allowed_html' => '<b>'],
        ],
      ],
    ])->save();
  }

  /**
   * Tests the question is reduced to text in every formatter.
   */
  #[DataProvider('formatters')]
  public function testQuestionMarkupIsStripped(string $formatter): void {
    $entity = $this->createEntity([
      ['question' => '<script>alert(1)</script><b>Question</b>', 'answer' => 'A', 'answer_format' => 'raw'],
    ]);
    $html = $this->renderField($entity, $formatter);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('<b>', $html);
    $this->assertStringContainsString('alert(1)Question', $html);
  }

  /**
   * Tests the answer is filtered by its own text format in every formatter.
   */
  #[DataProvider('formatters')]
  public function testAnswerIsFilteredByItsFormat(string $formatter): void {
    $entity = $this->createEntity([
      [
        'question' => 'Q',
        'answer' => '<b>kept</b><i>dropped</i><script>alert(1)</script>',
        'answer_format' => 'limited',
      ],
    ]);
    $html = $this->renderField($entity, $formatter);
    $this->assertStringContainsString('<b>kept</b>', $html);
    $this->assertStringNotContainsString('<i>', $html);
    $this->assertStringNotContainsString('<script', $html);
  }

  /**
   * Tests the default format filters answers that have no format of their own.
   */
  #[DataProvider('formatters')]
  public function testAnswerWithoutFormatUsesDefaultFormat(string $formatter): void {
    $entity = $this->createEntity([
      ['question' => 'Q', 'answer' => '<script>alert(1)</script>'],
    ]);
    $html = $this->renderField($entity, $formatter);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Tests the anchor name cannot break out of its attribute.
   */
  public function testAnchorNameIsEscaped(): void {
    $entity = $this->createEntity([
      ['question' => 'x" onmouseover="alert(1)', 'answer' => 'A', 'answer_format' => 'raw'],
    ]);
    $html = $this->renderField($entity, 'faqfield_anchor_list');
    $document = new \DOMDocument();
    $document->loadHTML($html);
    $xpath = new \DOMXPath($document);
    $this->assertSame(0, $xpath->query('//*[@onmouseover]')->length);
    $anchor = $xpath->query('//a[@name]')->item(0);
    $this->assertSame('faq-x"-onmouseover="alert(1)', $anchor->getAttribute('name'));
    $this->assertSame('#' . $anchor->getAttribute('name'), $anchor->getAttribute('href'));
  }

  /**
   * Tests the anchor name is built from the text of the question.
   */
  public function testAnchorNameIgnoresQuestionMarkup(): void {
    $entity = $this->createEntity([
      ['question' => '<b>Fish</b> and chips', 'answer' => 'A', 'answer_format' => 'raw'],
    ]);
    $html = $this->renderField($entity, 'faqfield_anchor_list');
    $document = new \DOMDocument();
    $document->loadHTML($html);
    $anchor = (new \DOMXPath($document))->query('//a[@name]')->item(0);
    $this->assertSame('faq-Fish-and-chips', $anchor->getAttribute('name'));
  }

  /**
   * Provides formatter plugin IDs.
   *
   * @return array<string, array<int, string>>
   *   Formatter IDs keyed by name.
   */
  public static function formatters(): array {
    return [
      'accordion' => ['faqfield_accordion'],
      'anchor list' => ['faqfield_anchor_list'],
      'definition list' => ['faqfield_definition_list'],
      'details' => ['faqfield_details'],
      'simple text' => ['faqfield_simple_text'],
    ];
  }

}
