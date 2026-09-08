<?php

namespace Drupal\club_ride_tools;

class GetRwgpsClient {

  protected $client;

  /**
   * Constructs a new GetRwgpsClient object.
   *
   * @param $http_client_factory \Drupal\Core\Http\ClientFactory
   */
  public function __construct($http_client_factory) {
    $config = \Drupal::config('rwgps.adminsettings');

    $this->client = $http_client_factory->fromOptions([
      'base_uri' => 'https://ridewithgps.com/routes/',
      'headers' => [
        'apikey' => $config->get('rwgps_api')
        ]
      ]);
  }

  /**
   * Get route information.
   *
   * @param int $routeId
   *
   * @return array
   */
  public function getRouteInfo($routeId, $ride_start) {

    $entity = \Drupal::entityTypeManager()->getStorage('club_route');

    if (is_numeric($routeId)) {

      $url = $routeId . '.json';

      try {
        $response = $this->client->request('GET', $url);

        if ($response->getStatusCode() == 200) {
          $routeInfo = json_decode($response->getBody(),true);

          // Set geofield values: starting location point; WKT version of the point; geofield point (polygon is NULL).
          $location_lon_lat = [$routeInfo['first_lng'], $routeInfo['first_lat']];
          $location_wkt = \Drupal::service('geofield.wkt_generator')->wktBuildPoint($location_lon_lat);
          $geofield_point = [
          'value' => $location_wkt,
          ];

          // Determine if route exists in Drupal: if yes then UPDATE, else CREATE.
          $oldRoute = $entity->load($routeId);

          if ($oldRoute) {      
            $oldRoute->name->value = $routeInfo['name'];
            $oldRoute->distance->value = round($routeInfo['distance']/1609);  // convert meters to miles
            $oldRoute->elevation_gain->value = round($routeInfo['elevation_gain']*3.281); // convert meters to feet
            $oldRoute->locality->value = $routeInfo['locality'];
            $oldRoute->state->value = $routeInfo['administrative_area'];
            $oldRoute->geofield->setValue([$geofield_point, NULL]);
            $oldRoute->field_ride_start->target_id = $ride_start;
            $oldRoute->created_at->value = $routeInfo['created_at'];
            $oldRoute->updated_at->value = $routeInfo['updated_at'];
            $oldRoute->unpaved->value = $routeInfo['unpaved_pct'];
            $oldRoute->terrain->value = $routeInfo['terrain'];
            $oldRoute->surface->value = $routeInfo['surface'];
            $oldRoute->save();
          } else {
            // Create new route entity.
            $newRoute = $entity->create([
              'rwgps_id' => $routeInfo['id'],
              'name' => $routeInfo['name'],
              'distance' => round($routeInfo['distance']/1609),  // convert meters to miles
              'elevation_gain' => round($routeInfo['elevation_gain']*3.281), // convert meters to feet
              'locality' => $routeInfo['locality'],
              'state' => $routeInfo['administrative_area'],
              'geofield' => [$geofield_point, NULL],
              'created_at' => $routeInfo['created_at'],
              'updated_at' => $routeInfo['updated_at'],
              'unpaved' => $routeInfo['unpaved_pct'],
              'terrain' => $routeInfo['terrain'],
              'surface' => $routeInfo['surface'],
            ]);
            $newRoute->save();
          }
        } // end if = 200
      } catch(\Exception $e) {
        \Drupal::logger('type')->error($e->getMessage());
        \Drupal::messenger()->addStatus('Route # not found at RideWithGPS: ' . $routeId);
      } // end catch
    } // end is_numeric
    else {
      \Drupal::messenger()->addStatus('Route number is not numeric: ' . $routeId);
    }
  } // end function
} // end class
