// ===== Checkout — jQuery + AJAX =====
(function ($) {
  'use strict';

  $('.payment-option').on('click', function () {
    var m = $(this).data('method');
    $('.payment-option').removeClass('selected'); $(this).addClass('selected');
    $('#payment-method').val(m);
    $('#bank-transfer-details').toggle(m === 'bank');
    $('#pod-details').toggle(m === 'pod');
  });

  $('.copy-btn').on('click', function () {
    var $b = $(this), text = String($b.data('copy'));
    var done = function () { var o = $b.text(); $b.text('Copied!'); setTimeout(function () { $b.text(o); }, 1800); };
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () { showToast('Copy failed — copy it manually.', 'error'); });
    else showToast('Copy not supported — copy it manually.', 'warning');
  });

  function alertMsg(m) {
    $('#checkout-alert').text('⚠️ ' + m).show();
    $('html, body').animate({ scrollTop: 0 }, 200);
  }

  $('#checkout-form').on('submit', function (e) {
    e.preventDefault();
    $('#checkout-alert').hide();
    var data = {};
    $.each($(this).serializeArray(), function (_, f) { data[f.name] = $.trim(f.value); });
    if (!data.fullname || !data.phone || !data.address || !data.city || !data.state) return alertMsg('Please fill in all required delivery details.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) return alertMsg('Please enter a valid email address.');

    var $btn = $('#btn-place-order').prop('disabled', true).text('Placing order…');
    api('api/place_order.php', data)
      .done(function (r) { location.href = r.redirect; })
      .fail(function (m) { alertMsg(m); $btn.prop('disabled', false).text('✅ Place Order Now'); });
  });
})(jQuery);
