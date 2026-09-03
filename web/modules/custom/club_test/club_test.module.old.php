<?php

use Drupal\Core\Routing\RouteMatchInterface;

use Drupal\encrypt\Entity\EncryptionProfile;

/*
use Drupal\club_test\DefaultUsers;
use Drupal\club_test\DefaultNodes;
use Drupal\club_test\DefaultTaxonomy;
use Drupal\club_test\DefaultMenus;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Datetime\DateTimePlus;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

use Drupal\club_schedule\LoadSchedule;

use Drupal\club_test\AddEventDates;
use Drupal\club_test\AddRecurDates;
use Drupal\club_test\AssignBasedOn;
use Drupal\club_test\CleanPermissions;
use Drupal\club_test\FixRides;
use Drupal\club_test\FixRoutes;
use Drupal\club_test\FixWed;
use Drupal\club_test\ListMaxID;
use Drupal\club_test\ResaveNodes;
*/

/*
 * Implement hook_help.
 * Test code is written to help page
 */
function club_test_help($route_name, RouteMatchInterface $route_match) {

  if ($route_name == 'help.page.club_test') {

    // Print UUIDs for exporting default content.
    // Add UUIDs to modules/custom/export_content/export_content.info.yml and enable module.
	  /*
	  DefaultNodes::getNode(310);                
    DefaultNodes::getNodes([2907,2953]);
    
    DefaultUsers::getUsers([858,859,860,861]); 
    
    DefaultTaxonomy::getTaxonomy('activities');
    DefaultTaxonomy::getTaxonomy('document_category');
    DefaultTaxonomy::getTaxonomy('gravel_categories');
    DefaultTaxonomy::getTaxonomy('image_category');
    DefaultTaxonomy::getTaxonomy('locations');
    DefaultTaxonomy::getTaxonomy('page_access');
    DefaultTaxonomy::getTaxonomy('positions');
    
    DefaultMenus::getMenu('main');
    DefaultMenus::getMenu('footer');
    DefaultMenus::getMenu('club-reports');
    die;
    */

    /*
    $menus = \Drupal::entityTypeManager()->getStorage('menu_link_content')
    ->loadByProperties(['menu_name' => 'main']);
    kint($menus);
    */
 
    //AddRecurDates::dropRecurDates();          /* Delete all recurring_dates before re-generating them. */
    //AddRecurDates::addRecurDates();           /* Re-save all recurring_rides to re-generate recurring_dates. */
    //CleanPermissions::dropPermissions();      /* Remove permissions related to uninstalled modules. */
    //LoadSchedule::loadSchedule();

    /* One-time fixes */
    //AddEventDates::addEventDates();           /* Copy Start date from Smart Date Range into Drupal Date field. */
    //FixRoutes::resaveRides('recurring_ride'); /* Save ride starting location in club_route field_ride_start. */
    //FixWed::convertWed();                     /* Convert Wednesday Morning Rotating Rides from recurring_ride to ride. */
    //FixRides::resaveRides('ride');            /* Resave every ride using db query to insert new fields. */
    //FixRides::resaveRides('recurring_ride'); 
    //ListMaxID::getMax();                     /* Write SQL code to run on server to reset auto_increment values on tables (database import issue). */

    /**
     * For new Master List: 
     * (1) Run FixRides for rides and recurring_rides to set Master = 1 (default value).
     * (2) Run AssignBasedOn to set Master = 0 and BasedOn = Master_NodeID 
     * 
     * AssignBasedOn::addBasedOn();
     * ResaveNodes::loadNodes('ride');
     */
  }
}
