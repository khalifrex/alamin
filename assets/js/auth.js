// ===== Sign in / register — jQuery + AJAX =====
(function ($) {
  'use strict';

  function showTab(which) {
    var reg = which === 'register';
    $('#tab-signin').toggleClass('active', !reg);
    $('#tab-register').toggleClass('active', reg);
    $('#form-signin').toggle(!reg);
    $('#form-register').toggle(reg);
    $('#auth-alert').hide();
  }
  $('#tab-signin, #switch-to-signin').on('click', function (e) { e.preventDefault(); showTab('signin'); });
  $('#tab-register, #switch-to-register').on('click', function (e) { e.preventDefault(); showTab('register'); });

  function alertMsg(msg) { $('#auth-alert').text('⚠️ ' + msg).show(); }

  function submitForm($form, action) {
    var data = { action: action, next: $form.data('next') };
    $.each($form.serializeArray(), function (_, f) { data[f.name] = f.value; });

    // quick client-side checks (the server validates again)
    if (action === 'register') {
      if (data.password.length < 8) return alertMsg('Password must be at least 8 characters.');
      if (data.password !== data.confirm_password) return alertMsg('Passwords do not match.');
    }
    var $btn = $form.find('button[type=submit]').prop('disabled', true);
    var label = $btn.text();
    $btn.text('Please wait…');

    api('api/auth.php', data)
      .done(function (r) { location.href = r.redirect; })
      .fail(function (msg) { alertMsg(msg); $btn.prop('disabled', false).text(label); });
  }

  $('#login-form').on('submit', function (e) { e.preventDefault(); submitForm($(this), 'login'); });
  $('#register-form').on('submit', function (e) { e.preventDefault(); submitForm($(this), 'register'); });

  if (location.hash === '#register') showTab('register');
})(jQuery);
