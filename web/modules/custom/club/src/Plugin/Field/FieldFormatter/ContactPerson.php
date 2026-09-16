<?php

namespace Drupal\club\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserDataInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for link to the user webform contact form.
 */
#[FieldFormatter(
  id: "contact_person",
  label: new TranslatableMarkup("Link to person contact form"),
  field_types: ["entity_reference"]
)]

class ContactPerson extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition,
    array $settings, $label, $view_mode, array $third_party_settings, 
    UserDataInterface $user_data) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label,$view_mode, $third_party_settings);
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('user.data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
 
      $contact_id = $entity->id();

      // Is personal contact form enabled? If no, display plain text.
      $disabled = $entity->field_disable_contact_form->value ? $entity->field_disable_contact_form->value:0;

      if ( $disabled == 1 ) {
        $elements[$delta] = [
          '#plain_text' => $entity->label(),
          '#cache' => [
            'tags' => $entity->getCacheTags(),
          ] 
        ];
      } 
      else {
        // 1. Build the URL object with query parameters
        $url = Url::fromRoute('entity.webform.canonical', ['webform' => 'contact_form'], [
          'query' => [
            'uid' => $contact_id,
            'username' => $entity->label()
          ],
          'attributes' => [
            'class' => ['webform-dialog', 'webform-dialog-normal'], // or webform-dialog-narrow / wide
          ],
        ]);
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $entity->label(),
          '#url' => $url,
          '#cache' => [
            'tags' => $entity->getCacheTags(),
          ]
        ];  
      }
    }  

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'user';
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity) {
    return $entity->access('view label', NULL, TRUE);
  }
}