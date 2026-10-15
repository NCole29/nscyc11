<?php

namespace Drupal\Tests\civicrm_entity\Kernel\Handler;

/**
 * Geocoding provider that returns fixed coordinates, for use in tests.
 *
 * Set the CiviCRM "geoProvider" setting to this class name.
 */
class StubGeocoder {

  /**
   * The latitude returned for any address.
   *
   * @var float
   */
  public static $latitude = 8;

  /**
   * The longitude returned for any address.
   *
   * @var float
   */
  public static $longitude = 20;

  /**
   * Geocodes an address.
   *
   * @param array $values
   *   The address values, which receive geo_code_1 and geo_code_2.
   * @param bool $stateName
   *   Unused.
   *
   * @return bool
   *   Always TRUE.
   *
   * @see \CRM_Utils_Geocode_Google::format()
   */
  public static function format(&$values, $stateName = FALSE) {
    $values['geo_code_1'] = static::$latitude;
    $values['geo_code_2'] = static::$longitude;
    return TRUE;
  }

}
