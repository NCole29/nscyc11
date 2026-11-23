<?php

namespace Drupal\Tests\civicrm_entity\Kernel;

/**
 * Tests for CiviCRM Views data.
 *
 * @group civicrim_entity
 */
class CivicrmViewsDataTest extends CivicrmEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
  ];

  /**
   * Test CiviCRM Address Views data.
   */
  public function testCivicrmAddressViewsData() {
    $views_data = $this->container->get('views.views_data');
    $civicrm_address = $views_data->get('civicrm_address');
    // Verify addresses have a relationship to contacts.
    $contact_relationship = $civicrm_address['contact_id']['relationship'];
    $this->assertEquals('civicrm_contact', $contact_relationship['base']);
    $this->assertEquals('id', $contact_relationship['base field']);

    $civicrm_contact = $views_data->get('civicrm_contact');
    $this->assertArrayHasKey('reverse__civicrm_address__contact_id', $civicrm_contact);
    $reverse_relationship = $civicrm_contact['reverse__civicrm_address__contact_id']['relationship'];
    $this->assertEquals('civicrm_address', $reverse_relationship['base']);
    $this->assertEquals('id', $reverse_relationship['base field']);
    $this->assertEquals('civicrm_contact', $reverse_relationship['field table']);
    $this->assertEquals('contact_id', $reverse_relationship['field field']);
  }

  /**
   * Tests that custom field filters offer the empty/not empty operators.
   */
  public function testCustomFieldFiltersAllowEmpty() {
    $views_data_handler = $this->container->get('entity_type.manager')->getHandler('civicrm_contact', 'views_data');
    $process = new \ReflectionMethod($views_data_handler, 'processViewsDataForCustomFields');
    $filter_manager = $this->container->get('plugin.manager.views.filter');

    $cases = [
      'Int' => ['data_type' => 'Int', 'html_type' => 'Text'],
      'Float' => ['data_type' => 'Float', 'html_type' => 'Text'],
      'Money' => ['data_type' => 'Money', 'html_type' => 'Text'],
      'String' => ['data_type' => 'String', 'html_type' => 'Text'],
      'Memo' => ['data_type' => 'Memo', 'html_type' => 'TextArea'],
      'Link' => ['data_type' => 'Link', 'html_type' => 'Link'],
      'Date' => ['data_type' => 'Date', 'html_type' => 'Select Date'],
      'Option list' => ['data_type' => 'String', 'html_type' => 'Select', 'option_group_id' => 1],
      'Country' => ['data_type' => 'Country', 'html_type' => 'Select Country'],
      'StateProvince' => ['data_type' => 'StateProvince', 'html_type' => 'Select State/Province'],
    ];

    $id = 1;
    foreach ($cases as $label => $metadata) {
      $metadata += [
        'id' => $id,
        'custom_group_id' => 1,
        'extends' => 'Individual',
        'table_name' => 'civicrm_value_test_1',
        'column_name' => "test_field_$id",
        'title' => 'Test',
        'label' => $label,
      ];
      $views_data = [];
      $process->invokeArgs($views_data_handler, [&$views_data, $metadata]);
      $filter = $views_data['civicrm_value_test_1']["test_field_$id"]['filter'];

      $operators = $filter_manager->createInstance($filter['id'], $filter)->operatorOptions();
      $this->assertArrayHasKey('empty', $operators, "$label filter offers 'Is empty (NULL)'.");
      $this->assertArrayHasKey('not empty', $operators, "$label filter offers 'Is not empty (NOT NULL)'.");
      $id++;
    }
  }

}
