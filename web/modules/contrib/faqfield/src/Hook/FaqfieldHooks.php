<?php

namespace Drupal\faqfield\Hook;

use Drupal\Core\Render\Markup;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for faqfield.
 */
class FaqfieldHooks {
  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.faqfield':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The FAQ Field module defines a field for storing frequently asked questions, for use with the Field module. See the <a href=":field_ui">Field module help page</a> for more information about fields.', [
          ':field_ui' => $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString() : '#',
        ]) . '</p>';
        return $output;
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
          // Simple text field formatter template.
      'faqfield_simple_text_formatter' => [
        'variables' => [
          'question' => NULL,
          'answer' => NULL,
          'answer_format' => NULL,
          'delta' => NULL,
          'question_tag' => 'h3',
        ],
        'template' => 'faqfield-simple-text-formatter',
      ],
          // Definition list field formatter template.
      'faqfield_definition_list_formatter' => [
        'variables' => [
          'items' => [],
        ],
        'template' => 'faqfield-definition-list-formatter',
      ],
          // Anchor list field formatter template.
      'faqfield_anchor_list_formatter' => [
        'variables' => [
          'items' => [],
          'list_type' => 'ul',
          'question_tag' => 'h3',
        ],
        'template' => 'faqfield-anchor-list-formatter',
      ],
          // jQuery Accordion field formatter template.
      'faqfield_jquery_accordion_formatter' => [
        'variables' => [
          'items' => [],
          'id' => NULL,
          'question_tag' => 'h3',
        ],
        'template' => 'faqfield-jquery-accordion-formatter',
      ],
          // Details field formatter template.
      'faqfield_details_formatter' => [
        'variables' => [
          'question' => NULL,
          'answer' => NULL,
          'answer_format' => NULL,
          'delta' => NULL,
        ],
        'template' => 'faqfield-details-formatter',
      ],
    ];
  }

  /**
   * Prepares variables for the simple text field formatter template.
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_faqfield_simple_text_formatter')]
  public function preprocessSimpleText(array &$variables): void {
    $variables['question'] = $this->plainQuestion($variables['question']);
    $variables['answer'] = $this->processedAnswer($variables['answer'], $variables['answer_format']);
  }

  /**
   * Prepares variables for the details field formatter template.
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_faqfield_details_formatter')]
  public function preprocessDetails(array &$variables): void {
    $variables['question'] = $this->plainQuestion($variables['question']);
    $variables['answer'] = $this->processedAnswer($variables['answer'], $variables['answer_format']);
  }

  /**
   * Prepares variables for the definition list field formatter template.
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_faqfield_definition_list_formatter')]
  public function preprocessDefinitionList(array &$variables): void {
    $this->prepareItems($variables['items']);
  }

  /**
   * Prepares variables for the anchor list field formatter template.
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_faqfield_anchor_list_formatter')]
  public function preprocessAnchorList(array &$variables): void {
    foreach ($variables['items'] as &$item) {
      // Twig escapes the name, so it is built from the text alone.
      $item['name'] = 'faq-' . str_replace(' ', '-', trim(strip_tags($item['question'])));
    }
    unset($item);
    $this->prepareItems($variables['items']);
  }

  /**
   * Prepares variables for the jQuery accordion field formatter template.
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_faqfield_jquery_accordion_formatter')]
  public function preprocessJqueryAccordion(array &$variables): void {
    $this->prepareItems($variables['items']);
  }

  /**
   * Strips tags from a question so it can be output as markup.
   */
  private function plainQuestion(string $question): Markup {
    return Markup::create(trim(strip_tags($question)));
  }

  /**
   * Builds the render array for an answer in its text format.
   *
   * @return array<string, mixed>
   *   A processed_text render array.
   */
  private function processedAnswer(?string $answer, ?string $format): array {
    return [
      '#type' => 'processed_text',
      '#text' => $answer,
      '#format' => $format,
    ];
  }

  /**
   * Prepares the question and answer of every item in a list.
   *
   * @param array<int, array<string, mixed>> $items
   *   The items, each with question, answer and answer_format keys.
   */
  private function prepareItems(array &$items): void {
    foreach ($items as &$item) {
      $item['question'] = $this->plainQuestion($item['question']);
      $item['answer'] = $this->processedAnswer($item['answer'], $item['answer_format']);
    }
  }

  /**
   * Implements hook_field_storage_config_create().
   *
   * Set the default cardinality to unlimited after a faqfield was created.
   */
  #[Hook('field_storage_config_create')]
  public function fieldStorageConfigCreate(FieldStorageConfigInterface $field_storage) {
    if ($field_storage->getType() == 'faqfield') {
      $field_storage->setCardinality(FieldStorageConfigInterface::CARDINALITY_UNLIMITED);
    }
  }

}
