<?php

namespace Drupal\Tests\civicrm_entity\Kernel\Handler;

use Drupal\views\Entity\View;
use Drupal\views\Views;

/**
 * Test the proximity filter.
 *
 * @group civicrm_entity
 */
final class FilterProximityTest extends KernelHandlerTestBase {

  /**
   * Address IDs keyed by label.
   *
   * @var int[]
   */
  protected $addressIds = [];

  /**
   * Tests that an address at the exact search coordinates is included.
   *
   * For some coordinates, such as 8,20, floating-point rounding pushes the
   * value passed to ACOS() above 1 when both points are identical, which
   * makes ACOS() return NULL and drops the row from the results.
   *
   * @see https://www.drupal.org/project/civicrm_entity/issues/3619745
   */
  public function testExactCoordinates() {
    $view = Views::getView('test_proximity');
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'proximity' => [
        'id' => 'proximity',
        'table' => 'civicrm_address',
        'field' => 'proximity',
        'plugin_id' => 'civicrm_entity_civicrm_address_proximity',
        'value' => [
          'value' => '00000',
          'city' => '',
          'state_province_id' => '',
          'distance' => 1,
          'distance_unit' => 'kilometers',
        ],
      ],
    ]);

    $this->executeView($view);

    $result_ids = array_map(fn ($row) => (int) $row->id, $view->result);
    sort($result_ids);
    $expected_ids = [$this->addressIds['exact'], $this->addressIds['near']];
    sort($expected_ids);

    $this->assertSame($expected_ids, $result_ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpFixtures() {
    /** @var \Drupal\civicrm_entity\CiviCrmApi $civicrm_api */
    $civicrm_api = $this->container->get('civicrm_entity.api');

    $addresses = [
      // Exactly at the search coordinates.
      'exact' => [8, 20],
      // About 150 meters away.
      'near' => [8.001, 20.001],
      // About 150 kilometers away.
      'far' => [9, 21],
    ];

    foreach ($addresses as $label => [$latitude, $longitude]) {
      $contact = $civicrm_api->save('Contact', [
        'contact_type' => 'Individual',
        'first_name' => $label,
        'last_name' => 'Proximity',
      ]);
      $address = $civicrm_api->save('Address', [
        'contact_id' => $contact['id'],
        'location_type_id' => 1,
        'street_address' => "$label street",
        'geo_code_1' => $latitude,
        'geo_code_2' => $longitude,
        'manual_geo_code' => 1,
      ]);
      $this->addressIds[$label] = (int) $address['id'];
    }

    // Geocode the search input to the same coordinates as the 'exact' address.
    \Civi::settings()->set('geoProvider', StubGeocoder::class);
    \CRM_Utils_GeocodeProvider::reset();

    View::create([
      'id' => 'test_proximity',
      'label' => 'Test proximity',
      'base_table' => 'civicrm_address',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'fields' => [
              'id' => [
                'id' => 'id',
                'table' => 'civicrm_address',
                'field' => 'id',
                'plugin_id' => 'field',
                'entity_type' => 'civicrm_address',
                'entity_field' => 'id',
              ],
            ],
          ],
        ],
      ],
    ])->save();
  }

}
