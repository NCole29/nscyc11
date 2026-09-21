(function ($, Drupal) {
  Drupal.behaviors.commentNotify = {
    attach(context) {
      $("#edit-notify, [id^='edit-notify--']", context)
        .on('change', function () {
          $("#edit-notify-type, [id^='edit-notify-type--']", context)[
            this.checked ? 'show' : 'hide'
          ]();
        })
        .trigger('change');
    },
  };
})(jQuery, Drupal);
