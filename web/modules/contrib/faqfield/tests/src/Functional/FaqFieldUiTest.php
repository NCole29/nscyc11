<?php

declare(strict_types=1);

namespace Drupal\Tests\faqfield\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entering, editing and displaying FAQ items through the UI.
 */
#[Group('faqfield')]
#[RunTestsInSeparateProcesses]
final class FaqFieldUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'faqfield',
    'field',
    'field_ui',
    'jquery_ui_accordion',
    'node',
  ];

  /**
   * Tests the main editing and display workflows.
   */
  public function testWorkflows(): void {
    $this->drupalCreateContentType(['type' => 'page']);
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_faq',
      'entity_type' => 'node',
      'type' => 'faqfield',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'page',
      'label' => 'FAQ',
    ])->save();
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent('field_faq', ['type' => 'faqfield_default'])
      ->save();
    $display_repository->getViewDisplay('node', 'page')
      ->setComponent('field_faq', ['type' => 'faqfield_simple_text'])
      ->save();
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node display',
      'administer node fields',
      'administer node form display',
      'bypass node access',
    ]));

    $this->assertWidgetSettingsChangeLabels();
    $node = $this->assertSeveralItemsCanBeAdded();
    $this->assertItemsCanBeEdited($node);
    $this->assertFormatterSettingsChangeMarkup($node);
  }

  /**
   * Asserts the widget titles set in form display show on the node form.
   */
  private function assertWidgetSettingsChangeLabels(): void {
    $this->drupalGet('admin/structure/types/manage/page/form-display');
    $this->submitForm([], 'field_faq_settings_edit');
    $this->submitForm([
      'fields[field_faq][settings_edit_form][settings][question_title]' => 'Ask us',
      'fields[field_faq][settings_edit_form][settings][answer_title]' => 'We reply',
    ], 'Update');
    $this->submitForm([], 'Save');

    $this->drupalGet('node/add/page');
    $this->assertSession()->fieldExists('Ask us');
    $this->assertSession()->pageTextContains('We reply');
  }

  /**
   * Asserts more than one question and answer can be saved and shown.
   */
  private function assertSeveralItemsCanBeAdded(): NodeInterface {
    $this->drupalGet('node/add/page');
    $this->submitForm([], 'field_faq_add_more');
    $this->submitForm([
      'title[0][value]' => 'FAQ page',
      'field_faq[0][question]' => 'First question',
      'field_faq[0][answer][value]' => 'First answer',
      'field_faq[1][question]' => 'Second question',
      'field_faq[1][answer][value]' => 'Second answer',
    ], 'Save');

    $node = $this->drupalGetNodeByTitle('FAQ page');
    $this->assertCount(2, $node->get('field_faq'));
    foreach (['First question', 'First answer', 'Second question', 'Second answer'] as $text) {
      $this->assertSession()->pageTextContains($text);
    }
    return $node;
  }

  /**
   * Asserts saved items are prefilled and an edit changes what is shown.
   */
  private function assertItemsCanBeEdited(NodeInterface $node): void {
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('field_faq[0][question]', 'First question');
    $this->assertSession()->fieldValueEquals('field_faq[1][answer][value]', 'Second answer');

    $this->submitForm(['field_faq[1][answer][value]' => 'Changed answer'], 'Save');
    $this->assertSession()->pageTextContains('Changed answer');
    $this->assertSession()->pageTextNotContains('Second answer');
  }

  /**
   * Asserts the formatter chosen and configured in manage display is used.
   */
  private function assertFormatterSettingsChangeMarkup(NodeInterface $node): void {
    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementExists('css', 'h3.faqfield-question');

    $this->drupalGet('admin/structure/types/manage/page/display/default');
    $this->submitForm(['fields[field_faq][type]' => 'faqfield_anchor_list'], 'Save');
    $this->submitForm([], 'field_faq_settings_edit');
    $this->submitForm([
      'fields[field_faq][settings_edit_form][settings][anchor_list_type]' => 'ol',
      'fields[field_faq][settings_edit_form][settings][question_tag]' => 'h2',
    ], 'Update');
    $this->submitForm([], 'Save');

    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementExists('css', '.faqfield-anchor-list ol li a');
    $this->assertSession()->elementExists('css', 'h2.faqfield-question');
    $this->assertSession()->elementNotExists('css', 'h3.faqfield-question');
  }

}
