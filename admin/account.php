<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin_page();
$admin_title = 'Account'; $admin_nav = 'account';
require __DIR__ . '/../includes/admin_header.php';
?>
<main class="admin-main narrow">
  <div class="page-head"><div><h1>Account</h1><p class="muted">Signed in as <strong><?= h($admin['username']) ?></strong>.</p></div></div>
  <section class="card">
    <h2>Change password</h2>
    <p class="muted small">The starter password is <code>admin123</code> — change it before anyone else can reach this site.</p>
    <form id="password-form" class="stack-form" novalidate>
      <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
      <label>New password (8+ characters)<input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
      <label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></label>
      <button type="submit" class="btn btn-primary">Update password</button>
    </form>
  </section>
</main>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
