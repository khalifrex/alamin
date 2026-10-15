// ===== Stallion admin — jQuery helpers for the orders and catalogue pages =====
(function ($) {
  'use strict';

  var csrf = $('meta[name="csrf-token"]').attr('content') || '';

  // POST JSON to a PHP endpoint; resolves with the reply, rejects with a readable message
  function api(url, data) {
    return $.ajax({
      url: url, method: 'POST', contentType: 'application/json', dataType: 'json',
      data: JSON.stringify(data || {}), headers: { 'X-CSRF-Token': csrf }
    }).then(null, function (xhr) {
      if (xhr.status === 401 && $('#admin-login-form').length === 0) { location.href = 'login.php'; }
      var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Network or server error. Is XAMPP running?';
      return $.Deferred().reject(msg, xhr).promise();
    });
  }

  function toast(message, type) {
    var $t = $('<div class="toast" role="status"></div>').addClass(type || 'success').text(message);
    $('.toast-container').append($t);
    setTimeout(function () { $t.fadeOut(200, function () { $t.remove(); }); }, 3500);
  }
  function busy($btn, on) { $btn.prop('disabled', on); }
  function reloadSoon() { setTimeout(function () { location.reload(); }, 500); }

  // ---------------------------------------------------------------- sign in / out
  $('#admin-login-form').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type=submit]'), $err = $('#login-error').prop('hidden', true);
    busy($btn, true);
    api('../api/admin_auth.php', { action: 'login', username: $('#username').val(), password: $('#password').val() })
      .done(function (r) { location.href = r.redirect; })
      .fail(function (msg) { $err.text(msg).prop('hidden', false); busy($btn, false); });
  });

  $(document).on('click', '.js-logout', function () {
    api('../api/admin_auth.php', { action: 'logout' }).always(function () { location.href = 'login.php'; });
  });

  // ---------------------------------------------------------------- order status dropdowns
  var STATUS_CLASS = { order_status: 'st-', payment_status: 'pay-' };
  $(document).on('focus', '.js-order-field', function () { $(this).data('prev', this.value); });
  $(document).on('change', '.js-order-field', function () {
    var $sel = $(this), field = $sel.data('field'), prev = $sel.data('prev'), payload = { id: $sel.data('id') };
    payload[field] = $sel.val();
    $sel.addClass('saving').prop('disabled', true);
    api('../api/admin_order.php', payload)
      .done(function (r) {
        $sel.attr('class', $sel.attr('class').replace(new RegExp(STATUS_CLASS[field] + '\\S+'), STATUS_CLASS[field] + r[field]));
        $sel.data('prev', r[field]);
        toast(field === 'order_status' ? 'Production: ' + r.order_label : 'Payment: ' + r.payment_label);
      })
      .fail(function (msg) { if (prev) $sel.val(prev); toast(msg, 'error'); })
      .always(function () { $sel.removeClass('saving').prop('disabled', false); });
  });

  // ---------------------------------------------------------------- catalogue
  function catalogCall(payload, $btn, reload) {
    busy($btn, true);
    return api('../api/admin_catalog.php', payload)
      .done(function (r) { toast(r.message); if (reload) reloadSoon(); })
      .fail(function (msg) { toast(msg, 'error'); })
      .always(function () { busy($btn, false); });
  }

  $(document).on('click', '.js-save-type', function () {
    var $row = $(this).closest('tr');
    catalogCall({ action: 'save_type', code: $row.data('code'), name: $row.find('.f-name').val(), is_active: $row.find('.f-active').is(':checked') }, $(this));
  });

  $(document).on('click', '.js-save-size', function () {
    var $row = $(this).closest('tr');
    catalogCall({ action: 'save_size', code: $row.data('code'), base_price: $row.find('.f-price').val(), is_active: $row.find('.f-active').is(':checked') }, $(this));
  });
  $(document).on('click', '.js-delete-size', function () {
    var $row = $(this).closest('tr');
    if (!confirm('Delete size ' + $row.data('code') + '? Customers will no longer see it. Old orders are not affected.')) return;
    catalogCall({ action: 'delete_size', code: $row.data('code') }, $(this), true);
  });
  $('.js-add-size').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    catalogCall({ action: 'save_size', code: $f.find('[name=code]').val(), base_price: $f.find('[name=base_price]').val(), is_active: true }, $f.find('button'), true);
  });

  $(document).on('click', '.js-save-color', function () {
    var $c = $(this).closest('.color-admin');
    catalogCall({ action: 'save_color', hex: $c.data('hex'), name: $c.find('.f-name').val(), is_active: $c.find('.f-active').is(':checked') }, $(this));
  });
  $(document).on('click', '.js-delete-color', function () {
    var $c = $(this).closest('.color-admin');
    if (!confirm('Delete this colour? Customers will no longer see it. Old orders are not affected.')) return;
    catalogCall({ action: 'delete_color', hex: $c.data('hex') }, $(this), true);
  });
  $('.js-add-color').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    catalogCall({ action: 'save_color', hex: $f.find('[name=hex]').val(), name: $f.find('[name=name]').val(), is_active: true }, $f.find('button'), true);
  });

  $('#fees-form').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    catalogCall({ action: 'save_fees', image_fee: $f.find('[name=image_fee]').val(), text_fee: $f.find('[name=text_fee]').val() }, $f.find('button'));
  });

  $('#add-tier').on('click', function () {
    $('#discount-rows').append('<div class="discount-row"><input type="number" class="d-qty" min="2" aria-label="Minimum quantity"> <span>or more pieces →</span> <input type="number" class="d-pct" min="0.01" max="90" step="0.01" aria-label="Percent off"> <span>% off</span> <button type="button" class="btn btn-danger js-remove-tier" aria-label="Remove tier">✕</button></div>');
  });
  $(document).on('click', '.js-remove-tier', function () { $(this).closest('.discount-row').remove(); });
  $('#discounts-form').on('submit', function (e) {
    e.preventDefault();
    var rows = $('#discount-rows .discount-row').map(function () {
      return { min_qty: $(this).find('.d-qty').val(), percent: $(this).find('.d-pct').val() };
    }).get();
    catalogCall({ action: 'save_discounts', rows: rows }, $(this).find('button[type=submit]'));
  });

  // ---------------------------------------------------------------- password
  $('#password-form').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this), $btn = $f.find('button[type=submit]'); busy($btn, true);
    api('../api/admin_auth.php', {
      action: 'change_password', current_password: $f.find('[name=current_password]').val(),
      new_password: $f.find('[name=new_password]').val(), confirm_password: $f.find('[name=confirm_password]').val()
    }).done(function (r) { toast(r.message); $f[0].reset(); })
      .fail(function (msg) { toast(msg, 'error'); })
      .always(function () { busy($btn, false); });
  });
})(jQuery);
