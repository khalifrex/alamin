<?php
require_once __DIR__ . '/includes/functions.php';
$next = $_GET['next'] ?? 'designer.php';
if (current_user()) { header('Location: ' . (in_array($next, ['checkout.php', 'my_orders.php'], true) ? $next : 'designer.php')); exit; }

$page_title = 'Sign In / Register';
$extra_css = ['assets/css/auth.css'];
$extra_js = ['assets/js/auth.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="auth-page" style="margin-top:-20px">

  <div class="auth-visual">
    <div class="auth-logo-big">ShirtCraft</div>
    <p class="auth-tagline">Wear your own story</p>
    <div class="auth-features-list">
      <div class="auth-feature"><div class="auth-feat-icon">🎨</div><div class="auth-feat-text"><h4>Design Studio</h4><p>Upload designs, add text, preview in 3D</p></div></div>
      <div class="auth-feature"><div class="auth-feat-icon">📦</div><div class="auth-feat-text"><h4>Order Tracking</h4><p>Track all your orders in one place</p></div></div>
      <div class="auth-feature"><div class="auth-feat-icon">💳</div><div class="auth-feat-text"><h4>Easy Payment</h4><p>Bank transfer or pay on delivery</p></div></div>
    </div>
  </div>

  <div class="auth-form-panel">
    <div class="auth-form-inner">

      <div id="auth-alert" style="display:none;background:#fee2e2;border:1px solid #ef4444;color:#b91c1c;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:0.88rem"></div>

      <div style="display:flex;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;margin-bottom:28px">
        <button id="tab-signin" type="button" class="auth-tab active">Sign In</button>
        <button id="tab-register" type="button" class="auth-tab">Create Account</button>
      </div>

      <!-- SIGN IN -->
      <div id="form-signin">
        <div class="auth-form-header"><h2>Welcome Back! 👋</h2><p>Sign in to order your custom designs</p></div>
        <form id="login-form" data-next="<?= h($next) ?>" novalidate>
          <div class="form-group">
            <label for="signin-email">Email Address</label>
            <input type="email" id="signin-email" name="email" placeholder="you@gmail.com" required autocomplete="email">
          </div>
          <div class="form-group">
            <label for="signin-password">Password</label>
            <input type="password" id="signin-password" name="password" placeholder="Enter your password" required autocomplete="current-password">
          </div>
          <button type="submit" class="btn-full">Sign In</button>
        </form>
        <div class="auth-switch">Don't have an account? <a href="#" id="switch-to-register">Create one free</a></div>
      </div>

      <!-- REGISTER -->
      <div id="form-register" style="display:none">
        <div class="auth-form-header"><h2>Create Account 🎉</h2><p>It takes less than a minute</p></div>
        <form id="register-form" data-next="<?= h($next) ?>" novalidate>
          <div class="form-group">
            <label for="reg-name">Full Name</label>
            <input type="text" id="reg-name" name="fullname" placeholder="First and Last Name" required autocomplete="name">
          </div>
          <div class="form-group">
            <label for="reg-email">Email Address</label>
            <input type="email" id="reg-email" name="email" placeholder="you@gmail.com" required autocomplete="email">
          </div>
          <div class="form-group">
            <label for="reg-phone">Phone Number <span style="color:var(--accent)">*required</span></label>
            <div class="phone-input-wrap">
              <div class="phone-flag">🌍
                <select name="country_code">
                  <option value="+234">+234</option><option value="+1">+1</option><option value="+44">+44</option>
                  <option value="+233">+233</option><option value="+254">+254</option>
                </select>
              </div>
              <input type="tel" id="reg-phone" name="phone" placeholder="08012345678" style="flex:1" required autocomplete="tel-national">
            </div>
            <p style="font-size:0.75rem;color:var(--gray);margin-top:4px">📞 Needed so we can reach you about delivery</p>
          </div>
          <div class="form-group">
            <label for="reg-password">Password</label>
            <input type="password" id="reg-password" name="password" placeholder="Min. 8 characters" required autocomplete="new-password">
          </div>
          <div class="form-group">
            <label for="reg-confirm">Confirm Password</label>
            <input type="password" id="reg-confirm" name="confirm_password" placeholder="Repeat your password" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn-full">Create Account &amp; Sign In →</button>
        </form>
        <div class="auth-switch">Already have an account? <a href="#" id="switch-to-signin">Sign in</a></div>
      </div>

    </div>
  </div>
</div>

<style>
.auth-tab{flex:1;padding:12px;font-weight:700;font-size:.95rem;background:#fff;color:var(--gray);transition:all .3s}
.auth-tab.active{background:var(--accent);color:#fff}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
