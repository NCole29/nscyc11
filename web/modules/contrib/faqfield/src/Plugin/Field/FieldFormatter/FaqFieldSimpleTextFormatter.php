<?php

namespace Drupal\faqfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'faqfield_simple_text' formatter.
 *
 * @FieldFormatter(
 *   id = "faqfield_simple_text",
 *   label = @Translation("Simple text"),
 *   field_types = {
 *     "faqfield"
 *   }
 * )
 */
class FaqFieldSimpleTextFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'question_tag' => 'h3',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    // An HTML tag for the question.
    $elements['question_tag'] = [
      '#type' => 'select',
      '#title' => $this->t('Question HTML tag'),
      '#default_value' => $this->getSetting('question_tag'),
      '#options' => [
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'h6' => 'h6',
      ],
      '#description' => $this->t('An HTML tag that wraps the question.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $default_format = $this->getFieldSetting('default_format');

    $elements = [];
    foreach ($items as $delta => $item) {
      // Decide whether to use the default format or the custom one.
      $format = (!empty($item->answer_format) ? $item->answer_format : $default_format);
      // Add each Q&A as page element,
      // to be rendered by the faqfield_simple_text_formatter template.
      $elements[$delta] = [
        '#theme' => 'faqfield_simple_text_formatter',
        '#question' => $item->question,
        '#answer' => $item->answer,
        '#answer_format' => $format,
        '#delta' => $delta,
        '#question_tag' => $this->getSetting('question_tag'),
      ];
    }

    return $elements;
  }

}
