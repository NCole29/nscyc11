<?php

namespace Drupal\bikeclub_reports\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a page with revenue for memberships and events by year
 *
 */
class RevenueReport implements ContainerInjectionInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  public function __construct(Connection $database, RendererInterface $renderer) {
    $this->database = $database;
    $this->renderer = $renderer;
  }
  
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('renderer')
    );
  }

  public function getRevenue() {

    // Aggregate revenue by year and financial type (event, membership, other)
    // Sort by descending year so that the table displays descendign years.
    $all = $this->database->query("SELECT year(receive_date) as year, financial_type_id as type,
        SUM(total_amount) as total, SUM(net_amount) as net, SUM(fee_amount) as fees
      FROM {civicrm_contribution}
      WHERE contribution_status_id = :cstatus and is_test = :test
      GROUP BY year, type
      ORDER BY year DESC",
      [':cstatus' => 1, 
       ':test' => 0,
      ])
     ->fetchALL();

     // Get financial type labels.
     $type_names = $this->database->query("SELECT id as type, name
      FROM `civicrm_financial_type`
      ORDER BY type")
      ->fetchALL();

    if ($all) {
      // Convert array of objects to array of arrays
      $result = json_decode(json_encode($all), true);
      $typeNames = json_decode(json_encode($type_names),true);

      // Return values from the 'type' column to use as array key.
      $typeLabel = array_column($typeNames, null, 'type');

     // Fill array of unique years and types.
      $years = [];
      $types = [];

      foreach($result as $key => $value){
        $years[] = $value['year'];
        $types[] = $value['type'];
      }

      // Use 'array_values' to reset keys after keeping unique values
      $years = array_values(array_unique($years));
      $types = array_values(array_unique($types));

      // Fill arrays to display years as rows and 3 columns for each revenue TYPE (Total, Net, Fees).

      // First header has YEAR and revenue type name.
      $header[-1] = 'Year';
      $i = 0; $j = 1;
      
        while($i < count($types)) {
          $type = $types[$i];
          $header[$i] = 
            [
              'data' => $typeLabel[$type]['name'],
              'colspan' => 3,
            ];
          $i++;
        }

      // Subheaders.
      $data = [];
      $data[''][0] = '';
      $i = 0; $j = 1;

        while($i < count($types)) {
          $data[''][$j] = 'Total';
          $data[''][$j+1] = 'Net';
          $data[''][$j+2] = 'Fees';
          $i++;
          $j = $j + 3;
        }

      // Fill rows with data. Data are sorted by descending year.
      $year = date("Y") - 1; // current year is not included in table

      foreach($result as $item) {

        if ($item['year'] < $year) {
          $year = $item['year'];
        }

        if ($item['year'] == $year and $item['type'] == $types[0]) {
          $j = 1;
          $type = $item['type'];
          $data[$year][0] = $year;
          $data[$year][$j] = number_format($item['total'],2);
          $data[$year][$j+1] = number_format($item['net'],2);
          $data[$year][$j+2] = number_format($item['fees'],2);
        }

        elseif ($item['year'] == $year and $item['type'] != $type) {
          $j = $j + 3;
          $type = $item['type'];
          $data[$year][$j] = number_format($item['total'],2);
          $data[$year][$j+1] = number_format($item['net'],2);
          $data[$year][$j+2] = number_format($item['fees'],2);
      }
    }

    // Build table.
    $title = "<h3'>Revenue by Year and Source</h3>";
    $footer = t("<p class='footer'>Table sums all completed contributions. Results may differ from CiviCRM contribution reports which exclude contributions from contacts that have been deleted.
    </p><small>Note: This report is not created by Views. Table is produced by bikeclub_reports/src/RevenueReport.php.</small><p><hr></p>");

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $data,
      '#empty' => t('No content has been found.'),
      '#attributes' => array (
        'class' => ['report-table'],
      ),
      '#cache' => array (
        'max-age' => 0,
      ),
    ];

    $tableHTML = $this->renderer->renderInIsolation($build);
    return [
      '#type' => '#markup',
      '#markup' => $title . $tableHTML . $footer,
      '#attached' => [
        'library' => [
		  'bikeclub/bikeclub-style',
        ],
      ]
    ];
  }
}
}
