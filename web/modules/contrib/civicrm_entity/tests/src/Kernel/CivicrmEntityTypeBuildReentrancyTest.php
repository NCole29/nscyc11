<?php

namespace Drupal\Tests\civicrm_entity\Kernel;

use Drupal\civicrm_entity\CiviCrmApiInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Tests that a nested entity type build does not call CiviCRM again.
 *
 * Building the CiviCRM entity types boots CiviCRM. While CiviCRM boots it
 * reads Drupal config, and a config override that looks up entity types
 * rebuilds the entity type definitions before the outer build has finished.
 * The test simulates that by rebuilding the definitions from inside the
 * CiviCRM API call that the entity type build makes.
 *
 * @group civicrm_entity
 */
class CivicrmEntityTypeBuildReentrancyTest extends CivicrmEntityTestBase {

  /**
   * Whether the 'entity' API call triggers a nested entity type build.
   *
   * @var bool
   */
  public static bool $triggerNestedBuild = FALSE;

  /**
   * The current nesting depth of the 'entity' API call.
   *
   * @var int
   */
  public static int $depth = 0;

  /**
   * The deepest nesting of the 'entity' API call that was seen.
   *
   * @var int
   */
  public static int $maxDepth = 0;

  /**
   * Whether the nested entity type build ran.
   *
   * @var bool
   */
  public static bool $nestedBuildDone = FALSE;

  /**
   * Whether the 'entity' API call fails after the nested entity type build.
   *
   * @var bool
   */
  public static bool $failAfterNestedBuild = FALSE;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    parent::alter($container);
    $container->set('civicrm_entity.api', new class($container->get('civicrm_entity.api')) implements CiviCrmApiInterface {

      /**
       * Constructs the decorator.
       *
       * @param \Drupal\civicrm_entity\CiviCrmApiInterface $inner
       *   The mocked CiviCRM API.
       */
      public function __construct(protected CiviCrmApiInterface $inner) {
      }

      /**
       * {@inheritdoc}
       */
      public function get($entity, array $params = []) {
        if ($entity !== 'entity' || !CivicrmEntityTypeBuildReentrancyTest::$triggerNestedBuild) {
          return $this->inner->get($entity, $params);
        }
        $depth = ++CivicrmEntityTypeBuildReentrancyTest::$depth;
        CivicrmEntityTypeBuildReentrancyTest::$maxDepth = max(CivicrmEntityTypeBuildReentrancyTest::$maxDepth, $depth);
        try {
          if ($depth === 1) {
            // Stands in for CiviCRM reading Drupal config while it boots,
            // where a config override looks up the entity types.
            \Drupal::entityTypeManager()->getDefinitions();
            CivicrmEntityTypeBuildReentrancyTest::$nestedBuildDone = TRUE;
            if (CivicrmEntityTypeBuildReentrancyTest::$failAfterNestedBuild) {
              throw new \RuntimeException('CiviCRM API call failed.');
            }
          }
          return $this->inner->get($entity, $params);
        }
        finally {
          CivicrmEntityTypeBuildReentrancyTest::$depth--;
        }
      }

      /**
       * {@inheritdoc}
       */
      public function delete($entity, array $params) {
        return $this->inner->delete($entity, $params);
      }

      /**
       * {@inheritdoc}
       */
      public function save($entity, array $params) {
        return $this->inner->save($entity, $params);
      }

      /**
       * {@inheritdoc}
       */
      public function getFields($entity, $action = 'create') {
        return $this->inner->getFields($entity, $action);
      }

      /**
       * {@inheritdoc}
       */
      public function getOptions($entity, $field_name) {
        return $this->inner->getOptions($entity, $field_name);
      }

      /**
       * {@inheritdoc}
       */
      public function getCount($entity, array $params = []) {
        return $this->inner->getCount($entity, $params);
      }

      /**
       * {@inheritdoc}
       */
      public function getSingle($entity, array $params = []) {
        return $this->inner->getSingle($entity, $params);
      }

      /**
       * {@inheritdoc}
       */
      public function getValue($entity, array $params = []) {
        return $this->inner->getValue($entity, $params);
      }

      /**
       * {@inheritdoc}
       */
      public function civicrmInitialize() {
        return $this->inner->civicrmInitialize();
      }

      /**
       * {@inheritdoc}
       */
      public function getCustomFieldMetadata($field_name) {
        return $this->inner->getCustomFieldMetadata($field_name);
      }

    });
  }

  /**
   * Tests that the nested build skips CiviCRM and the outer build completes.
   */
  public function testNestedBuildDoesNotCallCivicrmAgain(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->clearCachedDefinitions();

    static::$depth = 0;
    static::$maxDepth = 0;
    static::$nestedBuildDone = FALSE;
    static::$triggerNestedBuild = TRUE;
    try {
      $definitions = $entity_type_manager->getDefinitions();
    }
    finally {
      static::$triggerNestedBuild = FALSE;
    }

    $this->assertTrue(static::$nestedBuildDone, 'The nested entity type build ran.');
    $this->assertSame(1, static::$maxDepth, 'The nested entity type build did not call CiviCRM.');
    $this->assertArrayHasKey('civicrm_event', $definitions);
    $this->assertArrayHasKey('civicrm_contact', $definitions);
  }

  /**
   * Tests that a failed outer build leaves no incomplete definitions cached.
   *
   * The skipped nested build caches definitions without the CiviCRM entity
   * types. If the outer build then fails, those must not be used later.
   */
  public function testFailedOuterBuildClearsCachedDefinitions(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->clearCachedDefinitions();

    static::$depth = 0;
    static::$nestedBuildDone = FALSE;
    static::$triggerNestedBuild = TRUE;
    static::$failAfterNestedBuild = TRUE;
    try {
      $entity_type_manager->getDefinitions();
      $this->fail('The entity type build did not fail.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('CiviCRM API call failed.', $e->getMessage());
    }
    finally {
      static::$triggerNestedBuild = FALSE;
      static::$failAfterNestedBuild = FALSE;
    }

    $this->assertTrue(static::$nestedBuildDone, 'The nested entity type build ran.');
    $this->assertFalse(\Drupal::cache('discovery')->get('entity_type'), 'No entity type definitions are cached.');
    $this->assertTrue($entity_type_manager->hasDefinition('civicrm_contact'), 'The next build adds the CiviCRM entity types.');
  }

}
