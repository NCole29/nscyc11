<?php

namespace Drupal\club_test;

use Drupal\Core\Config\Entity\Query\Query;
use Drupal\Core\Entity\EntityInterface;

class LeaderRoleValidation {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity query service.
   *
   * @var \Drupal\Core\Config\Entity\Query\Query
   */
  protected $entityQuery;

 /**
   * An array of position and position roles from club positions taxonomy.
   *
   * @var array
   */
  protected $position_roles;
  
 /**
   * Unique list of 'position' roles.
   *
   * @var array
   */
  protected $proles;
  
/**
   * Constructs a LeaderRolesValidation object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityQuery $entityQuery) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityQuery = $entityQuery;
  }


}
