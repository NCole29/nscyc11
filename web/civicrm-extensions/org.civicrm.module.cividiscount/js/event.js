CRM.$(function($) {

  var additionalParticipants = $('#additional_participants');

  additionalParticipants.change(function () {
    if (parseInt(CRM.$('#additional_participants :selected').text()) < 2) {
      // No need to override for single participant
      return;
    }
    if (typeof showHidePayment == 'function') {
      // If discount makes all possible lineitems 0 then don't show payment for multiple participants
      if (CRM.vars.cividiscount.discountApplied === true && CRM.vars.cividiscount.totalAmountZero === true) {
        console.log('cividiscount: amount is zero');
        showHidePayment(true);
      }
    }
  });

  // Trigger on load - required if page is reloaded after discount applied
  additionalParticipants.trigger('change');

  // When a price is selected payment info is shown again. Hide it if we need to
  $('#priceset [price]').on('change', function() {
    $('#additional_participants').trigger('change');
  });

});
