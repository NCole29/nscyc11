<?php

namespace Drupal\bikeclub\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for link to the user contact form.
 */
#[FieldFormatter(
  id: "user_contact_link",
  label: new TranslatableMarkup("Link to contact form"),
  field_types: ["entity_reference"]
)]

class UserContactFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

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
      $enabled = $this->userData->get('contact', $contact_id, 'enabled');

      if ( !$enabled ) {
        $elements[$delta] = [
          '#plain_text' => $entity->label(),
          '#cache' => [
            'tags' => $entity->getCacheTags(),
          ] 
        ];
      } 
      else {
        $url = \Drupal\Core\Url::fromRoute('entity.user.contact_form', ['user' => $contact_id ]);
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