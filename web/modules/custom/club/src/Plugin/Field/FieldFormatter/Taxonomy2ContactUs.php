<?php

namespace Drupal\club\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

/**
 * Plugin implementation of the 'entity reference taxonomy term' formatter.(new link to webform).
 */
#[FieldFormatter(
  id: 'entity_reference_mailboxes',
  label: new TranslatableMarkup('Link to contact_us webform'),
  field_types: [
    'entity_reference',
  ],
)]
class Taxonomy2ContactUs extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $parent_entity = $items->getEntity();
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $entity) {
      $value = $entity->id();
      $label = $entity->label();

      if ($label <> 'Personal contact form') {
        $url = Url::fromRoute('entity.webform.canonical', ['webform' => 'contact_us'], [
          'query' => [
            'id' => 0,
            'mail_to' => $value
          ],
          'attributes' => [
            'class' => ['webform-dialog', 'webform-dialog-normal'], 
          ],
        ]);
        $elements[] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $url,
        ];  
      }
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for taxonomy terms.
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'taxonomy_term';
  }

}
