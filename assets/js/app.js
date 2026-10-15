// ===== Stallion — shared jQuery helpers (loaded on every page) =====
(function ($) {
  'use strict';

  // Send the CSRF token with every AJAX request
  $.ajaxSetup({
    beforeSend: function (xhr) {
      xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content') || '');
    }
  });

  // POST JSON to a PHP endpoint, resolve with the parsed reply, reject with a readable message
  window.api = function (url, data) {
    return $.ajax({
      url: url, method: 'POST', contentType: 'application/json',
      data: JSON.stringify(data || {}), dataType: 'json'
    }).then(null, function (xhr) {
      var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Network or server error. Is XAMPP running?';
      return $.Deferred().reject(msg, xhr).promise();
    });
  };

  window.showToast = function (message, type, duration) {
    type = type || 'success';
    var icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    var $c = $('.toast-container');
    if (!$c.length) $c = $('<div class="toast-container"></div>').appendTo('body');
    var $t = $('<div class="toast"></div>').addClass(type);
    $('<span class="toast-icon"></span>').text(icons[type] || icons.info).appendTo($t);
    $('<span class="toast-text"></span>').text(message).appendTo($t);   // .text() => no HTML injection
    $('<span class="toast-close">✕</span>').on('click', function () { $t.remove(); }).appendTo($t);
    $c.append($t);
    setTimeout(function () { $t.remove(); }, duration || 4000);
  };

  window.formatNaira = function (n) {
    return '₦' + Number(n).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  $(function () {
    // loading splash
    setTimeout(function () { $('#loading-screen').addClass('hidden'); }, 600);

    // mobile menu + user dropdown
    $('#hamburger').on('click', function () { $('.nav-links').toggleClass('mobile-open'); });
    $('#nav-user-avatar').on('click', function (e) { e.stopPropagation(); $('#user-dropdown').toggleClass('open'); });
    $(document).on('click', function () { $('#user-dropdown').removeClass('open'); });

    // sign out (AJAX)
    $('#nav-logout').on('click', function (e) {
      e.preventDefault();
      api('api/auth.php', { action: 'logout' }).done(function (r) { location.href = r.redirect; });
    });
  });
})(jQuery);
