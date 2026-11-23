/**
 * @file
 * Binds jQuery accordions to provided FAQ Fields.
 */

(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.faqfieldAccordion = {
    attach(context) {
      if (drupalSettings.faqfield !== undefined) {
        // Bind the accordion to any defined faqfield accordion formatter with
        // provided settings.
        Object.keys(drupalSettings.faqfield).forEach((selector) => {
          $(selector, context).accordion(drupalSettings.faqfield[selector]);
        });
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
