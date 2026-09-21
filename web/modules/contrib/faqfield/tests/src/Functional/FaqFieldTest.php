<?php

declare(strict_types=1);

namespace Drupal\Tests\faqfield\Functional;

use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test faqfield functionality.
 */
#[Group('faqfield')]
final class FaqFieldTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'faqfield',
    'field',
    'jquery_ui_accordion',
    'node',
  ];

  /**
   * Test callback.
   */
  public function testSomething(): void {
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'access content overview',
      'administer nodes',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    // Create a test content type.
    $this->drupalCreateContentType(['type' => 'page']);

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_faq_field',
      'entity_type' => 'node',
      'type' => 'faqfield',
    ]);
    $fieldStorage->save();
    $fieldConfig = FieldConfig::create([
      'field_name' => $fieldStorage->getName(),
      'field_storage' => $fieldStorage,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => $this->randomString(),
    ]);
    $fieldConfig->save();

    // Assign widget settings for the default form mode.
    $settings = [
      'region' => 'content',
      'settings' => [
        'max_filesize' => '1GB',
      ],
      'third_party_settings' => [],
    ];

    $display_repository = \Drupal::service('entity_display.repository');
    assert($display_repository instanceof EntityDisplayRepository);
    $display_repository->getFormDisplay('node', 'page')
      ->setComponent($fieldStorage->getName(), ['type' => 'faqfield_default'] + $settings)
      ->save();

    $this->drupalGet('/node/add/page');
    $question = $this->randomString();
    $answer = $this->randomString();
    $this->submitForm([
      'Title' => $this->randomString(),
      'field_faq_field[0][question]' => $question,
      'field_faq_field[0][answer][value]' => $answer,
    ], 'Save');
    $assert = $this->assertSession();

    $formatters = [
      'faqfield_accordion',
      'faqfield_anchor_list',
      'faqfield_definition_list',
      'faqfield_details',
      'faqfield_simple_text',
    ];
    foreach ($formatters as $formatter) {
      $display_repository->getViewDisplay('node', 'page')
        ->setComponent($fieldStorage->getName(), ['type' => $formatter])
        ->save();
      $this->drupalGet('node/1');
      $assert->pageTextContains($question);
      $assert->pageTextContains($answer);
    }
  }

}
