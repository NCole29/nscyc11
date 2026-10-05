<?php

namespace Drupal\club\Repository;

use Drupal\Core\Database\Connection;

/**
 * Repository service to fetch data from a MySQL database view.
 */
class MySqlViewRepository {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * Constructs a new MySqlViewRepository object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Fetch data from the MySQL view.
   *
   * @return array
   *   An array of records from the database view.
   */
  public function getViewData(): array {
    // Treat the MySQL view exactly like a normal read-only table
    $query = $this->database->select('unique_rides', 'v')
      ->fields('v', ['nid', 'title', 'created']);
    
    return $query->execute()->fetchAll();
  }
}
