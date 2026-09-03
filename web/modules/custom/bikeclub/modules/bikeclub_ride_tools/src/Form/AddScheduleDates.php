<?php

namespace Drupal\bikeclub_ride_tools\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AddScheduleDates extends FormBase {

  /**
   * The schedule load type: initial(0) or not(1).
   *
   * @var integer
   */
  protected $loadType;

  /**
   * club_schedule entity storage.
   *
   * @var integer
   */
  protected $schedule_storage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_schedule_dates';
  }

  public function getDates() {
    $this->schedule_storage = $this->entityTypeManager->getStorage('club_schedule'); 
    $query = $this->schedule_storage->getAggregateQuery();
    $query
      ->accessCheck(FALSE)
      ->aggregate('schedule_date', 'MIN', NULL)
      ->aggregate('schedule_date', 'MAX', NULL);
    $minmax = $query->execute();
    
    return $minmax;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get range of dates in the club_schedule table.
    $minmax = $this->getDates();

    if (!is_null($minmax[0]["schedule_date_min"])) {

      $this->loadType = 1; // Not an initial load.

      $min = $minmax[0]["schedule_date_min"];
      $max = $minmax[0]["schedule_date_max"];

      // Create DrupalDateTime objects.
      $min_datetime = new DrupalDateTime($min); 
      $max_datetime = new DrupalDateTime($max); 

      // Format the date
      $min_date = $this->dateFormatter->format($min_datetime->getTimestamp(), 'custom', 'm-d-Y'); 
      $max_date = $this->dateFormatter->format($max_datetime->getTimestamp(), 'custom', 'm-d-Y'); 

      $form['instructions'] = [
        '#type' => 'markup',
        '#markup' => "<p>The database contains Schedule dates from <strong>$min_date</strong> to <strong>$max_date</strong>.<br>Click the button below to add 
        an additional 3 years of dates to the database.</p>" 
      ];
    } else {
      $this->loadType = 0; // Initial load.
      $form['instructions'] = [
        '#type' => 'markup',
        '#markup' => "<p>Click the button below to add 3 years of dates to the database.</p>" 
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add dates'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * Add records to the club_schedule table for each day, for 5 years.
   * $load = 0 (initial load), 1 (subsequent loads) 
   */
  public function loadSchedule($load) {
    $now = time();

    if ($load == 0) {
      // Initial load during install starts with current year.
      $startYr = date("Y");
    } else {
      // Start with year after max year in data table.
      $query = $this->schedule_storage->getAggregateQuery();
      $maxdate = $query
        ->accessCheck(FALSE)
        ->aggregate('schedule_date', 'MAX', NULL)
        ->execute();
      $startYr = substr($maxdate[0]['schedule_date_max'],0,4) + 1;
    }
    for ($year = $startYr; $year < ($startYr + 3); $year++) {         
      $jan1 = strtotime("First day Of January $year");

      for($x = 0; $x < 365; $x++){
        $timestamp = strtotime("+$x day", $jan1);
        $weekday = date('l', $timestamp);

        $date = DrupalDateTime::createFromTimestamp($timestamp, 'UTC')->format('Y-m-d'); 		

        $newDate = $this->schedule_storage->create([
          'weekday' => $weekday,
          'schedule_date' => $date,
          'created' => $now,
          'changed' => $now,
          'langcode' => "en",
        ]);
        $newDate->save();
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->loadSchedule($this->loadType); 
    $this->messenger()->addStatus($this->t('Three years of dates have been added.'));
  }
  
}