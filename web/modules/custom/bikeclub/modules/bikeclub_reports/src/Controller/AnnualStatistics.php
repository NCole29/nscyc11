<?php

namespace Drupal\bikeclub_reports\Controller;

use Drupal\Core\Database\Connection; 
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response; 

/**
 * Program must be run on December 31.
 */
class AnnualStatistics implements ContainerInjectionInterface {

  protected $database;
  protected $entityTypeManager;
  protected $loggerFactory;

  /**
   * The club statistic storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $statisticStorage;

  public function __construct(
    Connection $database, 
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    EntityStorageInterface $statistic_storage)
    {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->statisticStorage = $statistic_storage;
  }

   public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager')->getStorage('club_statistic'),
    );
   }

  /**
   * {@inheritdoc}
   */
  public function getTermId($name) {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'statistics','name' => $name, ]);
    $term = reset($term);
    $this->term_id = $term->id();
    return $this->term_id;
  }
 
  public function saveStatistic($term, $stat, $yr) {
    $saveStat = $this->statisticStorage->create([
      'statistic' => $this->getTermId($term),
      'year' => $yr,
      'number' => $stat,
    ]);
    $saveStat->save();
  }

  public function tabulate() {

    //Pass year in query string to populate past years, then disable the line of code.
    // https://nscyc.ddev.site/admin/help/club_test?year=2025
    $yr = \Drupal::request()->query->get('year');

    if (is_null($yr)) {
      $yr = date('Y');
    }

    $jan1  = date("$yr-01-01"); 
    $dec31 = date("$yr-12-31");

    //************ Count of rides and canceled rides ************//
    $node_storage = $this->entityTypeManager->getStorage('node');

    $bundles = ['ride', 'recurring_dates'];

    foreach ($bundles as $bundle) {
      $count[$bundle]['total'] = $node_storage->getQuery()
        ->condition('type', $bundle, '=')
        ->condition('field_date', $jan1, '>=')
        ->condition('field_date', $dec31, '<=')
        ->accessCheck(FALSE)
        ->count()->execute();

      $count[$bundle]['canceled']  = $node_storage->getQuery()
        ->condition('type', $bundle, '=')
        ->condition('field_date', $jan1, '>=')
        ->condition('field_date', $dec31, '<=')
        ->condition('field_cancel', 1, '=')
        ->accessCheck(FALSE)
        ->count()->execute();  
    }
    $scheduled_rides = $count['ride']['total'] + $count['recurring_dates']['total'];
    $canceled_rides  = $count['ride']['canceled'] + $count['recurring_dates']['canceled'];

    // Save statistics to custom club_statistic entity.
    $this->saveStatistic('Rides',$scheduled_rides, $yr);
    $this->saveStatistic('Canceled rides',$canceled_rides, $yr);
   

    //********* Count of current, new, and expired memberships *********//
    $memberships = $this->database->select('civicrm_membership', 'm');
    $memberships->leftjoin('civicrm_contact', 'c', 'm.contact_id = c.id');
    $memberships
      ->fields('m', ['status_id','start_date','end_date'])
      ->condition('m.status_id', [1,2], 'IN')
      ->condition('m.is_test', 0, '=')
      ->condition('c.is_deleted', 0, '=');

    // Total members at year-end.  
    $member_count = $memberships
      ->condition('m.status_id', [1,2], 'IN')
      ->countQuery()->execute()->fetchField();

    // New members this year (subset of all members).
    $new_count = $memberships
      ->condition('m.status_id', [1,2], 'IN')
      ->condition('m.start_date', $jan1, '>=')
      ->condition('m.start_date', $dec31, '<=')
      ->countQuery()->execute()->fetchField();

    // Expired memberships during the year.
    $expired = $this->database->select('civicrm_membership', 'm');
    $expired->leftjoin('civicrm_contact', 'c', 'm.contact_id = c.id');
    $expired
      ->condition('m.status_id', 4, '=')
      ->condition('m.end_date', $jan1, '>=')
      ->condition('m.end_date', $dec31, '<=')
      ->condition('m.is_test', 0, '=')
      ->condition('c.is_deleted', 0, '=');
    $expired_count = $expired->countQuery()->execute()->fetchField();

    // Save statistics to custom club_statistic entity.
    $this->saveStatistic('Members',$member_count, $yr);
    $this->saveStatistic('New members',$new_count, $yr);
    $this->saveStatistic('Expired members',$expired_count, $yr);

  
    //*** Count of persons signing nonmember waiver (total, joined after ride, joined before ride) ***//

    // Get activity_type_id for nonmember rider activity.
    $query = $this->database->select('civicrm_option_value','v')
      ->fields('v',['value'])
      ->condition('name', 'Attended ride as non-member');
    $activity_type_id = $query->execute()->fetchField();

    // Get activity records and contact_id for nonmember riders.
    $query = $this->database->select('civicrm_activity', 'a');
    $query->leftjoin('civicrm_activity_contact', 'ac', 'a.id = ac.activity_id');
    $query
      ->fields('a', ['id','activity_type_id', 'activity_date_time', 'subject'])
      ->fields('ac', ['contact_id'])
      ->condition('ac.record_type_id', 3)
      ->condition('a.activity_type_id', $activity_type_id, '=')
      ->condition('a.activity_date_time', $jan1, '>=')
      ->condition('a.activity_date_time', $dec31, '<=');
    $nonmembers = $query->execute()->fetchAll();

    $num_nonmembers = count($nonmembers);
    $num_joined = 0;
    $num_members = 0;

    // Count nonmember riders with memberships.
    foreach($nonmembers as $nonmember) {
      $query = $this->database->select('civicrm_membership', 'm');
      $query->leftjoin('civicrm_contact', 'c', 'm.contact_id = c.id');
      $query
        ->fields('m', ['status_id','start_date','end_date'])
        ->condition('m.contact_id', $nonmember->contact_id)
        ->condition('m.status_id', [1,2,4], 'IN')
        ->condition('m.is_test', 0, '=')
        ->condition('c.is_deleted', 0, '=');
      $membership = $query->execute()->fetchAll();
      $membership = reset($membership);

      $nonmember->activity_date_time = substr($nonmember->activity_date_time,0,10); // To compare with membership date.
      
      $nonmember->joined = ($membership->start_date >= $nonmember->activity_date_time) ? 1 : 0;
      $nonmember->member = ($membership->start_date >0 and $membership->start_date < $nonmember->activity_date_time) ? 1 : 0;

      $num_joined  = $num_joined + $nonmember->joined;
      $num_members = $num_members + $nonmember->member;
    }
    // Save statistics to custom club_statistic entity.
    $this->saveStatistic('Nonmember riders',$num_nonmembers, $yr);
    $this->saveStatistic('Nonmembers joined after',$num_joined, $yr);
    $this->saveStatistic('Nonmembers joined before',$num_members, $yr);


    // Log results
    $logger = $this->loggerFactory->get('bikeclub_stats');
    $logger->info('End-of-year counts. Rides: @rides scheduled, @cancel cancelled. Members: @members total, @new new, @expired expired. 
    Nonmember riders: @nonmembers total, @joined joined, @past already member.',
     ['@rides'  => print_r($stat['rides'], TRUE), 
      '@cancel' => print_r($stat['canceled_rides'], TRUE),
      '@members' => print_r($members, TRUE),
      '@new' => print_r($new, TRUE),
      '@expired' => print_r($expired, TRUE), 
      '@nonmembers' => print_r($num_nonmembers, TRUE),
      '@joined' => print_r($num_joined, TRUE),
      '@past' => print_r($num_members, TRUE), ]);

    return new Response('', Response::HTTP_NO_CONTENT); 
 }

}
