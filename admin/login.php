<?php
require_once __DIR__ . '/../includes/functions.php';
if (current_admin()) { header('Location: index.php'); exit; }
$admin_title = 'Sign in';
require __DIR__ . '/../includes/admin_header.php';
?>
<main class="login-card">
  <div class="login-logo" style="display:flex;align-items:center;gap:12px"><?= admin_logo(52) ?><span>Admin</span></div>
  <p class="muted">Sign in to manage orders, prices and the catalogue.</p>
  <div id="login-error" class="alert alert-error" role="alert" hidden></div>
  <form id="admin-login-form" novalidate>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  </form>
  <p class="muted small"><a href="../index.php">← Back to the shop</a></p>
</main>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
