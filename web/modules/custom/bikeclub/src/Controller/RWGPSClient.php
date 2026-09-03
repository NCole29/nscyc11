<?php

namespace Drupal\bikeclub\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides a service to retrieve RWGPS route data.
 */
class RwgpsClient {

/**
  * The config factory.
  *
  * @var \Drupal\Core\Config\ConfigFactoryInterface
  */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Construct a new GetRwgpsClient object.
   *
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   The HTTP client factory.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ClientFactory $httpClientFactory,
    MessengerInterface $messenger
  ) {
    $this->configFactory     = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClientFactory = $httpClientFactory;
    $this->messenger         = $messenger;
  }

  /**
   * Get route information.
   *
   * @param int $routeId
   *
   * @return array
   */
  public function getRouteInfo($routeId, $ride_start) {
   
    $rwgpsKey = $this->configFactory->get('bikeclub.adminsettings')->get('rwgps_api');

    $client = $this->httpClientFactory->fromOptions([
      'base_uri' => 'https://ridewithgps.com/routes/',
      'headers' => [
        'apikey' => $rwgpsKey
      ]
    ]);
  
    // Get route Ids from node.
    $entity = $this->entityTypeManager->getStorage('club_route');

    $url = $routeId . '.json';

    try {
      $response = $client->request('GET', $url);

      if ($response->getStatusCode() == 200) {
        $routeInfo = json_decode($response->getBody(),true);
        d($routeInfo);
        die;

        // Set geofield values: starting location point; WKT version of the point; geofield point (polygon is NULL).
        $location_lon_lat = [$routeInfo['first_lng'], $routeInfo['first_lat']];
        $location_wkt = \Drupal::service('geofield.wkt_generator')->wktBuildPoint($location_lon_lat);
        $geofield_point = [
        'value' => $location_wkt,
        ];

        // Determine if route exists in Drupal: if yes then UPDATE, else CREATE.
        $oldRoute = $entity
          ->load($routeId);

        if (!empty($oldRoute)) {
          $oldRoute->name->value = $routeInfo['name'];
          $oldRoute->distance->value = round($routeInfo['distance']/1609);  // convert meters to miles
          $oldRoute->elevation_gain->value = round($routeInfo['elevation_gain']*3.281); // convert meters to feet
          $oldRoute->unpaved->value = $routeInfo['unpaved_pct'];
          $oldRoute->terrain->value = $routeInfo['terrain'];
          $oldRoute->surface->value = $routeInfo['surface'];
          $oldRoute->locality->value = $routeInfo['locality'];
          $oldRoute->state->value = $routeInfo['administrative_area'];
          $oldRoute->geofield->setValue([$geofield_point, NULL]);
          $oldRoute->ride_start = $ride_start;
          $oldRoute->created_at->value = $routeInfo['created_at'];
          $oldRoute->updated_at->value = $routeInfo['updated_at'];
          $oldRoute->save();
        } else {
          // Create new route entity.
          $newRoute = $entity->create([
            'rwgps_id' => $routeInfo['id'],
            'name' => $routeInfo['name'],
            'distance' => round($routeInfo['distance']/1609),  // convert meters to miles
            'elevation_gain' => round($routeInfo['elevation_gain']*3.281), // convert meters to feet
            'unpaved' => $routeInfo['unpaved_pct'],
            'terrain' => $routeInfo['terrain'],
            'surface' => $routeInfo['surface'],
            'locality' => $routeInfo['locality'],
            'state' => $routeInfo['administrative_area'],
            'ride_start' => $ride_start,
            'geofield' => [$geofield_point, NULL],
            'created_at' => $routeInfo['created_at'],
            'updated_at' => $routeInfo['updated_at'],
          ]);
          $newRoute->save();
        }
      } // end if = 200
    } catch(\Exception $e) {
      $this->logger->error($e->getMessage());
      $this->messenger->addStatus('Route # not found at RideWithGPS: ' . $routeId);
    } // end catch
  } // end is_numeric

} 
