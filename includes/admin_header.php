<?php
// Shared top bar for the admin pages. Set $admin_title and $admin_nav ('orders'|'catalog'|'account') before including.
require_once __DIR__ . '/functions.php';

/** The shop logo mark as inline SVG, so it never depends on a separate image file. */
function admin_logo(int $size = 36): string {
    static $n = 0; $n++; $id = 'lg' . $n;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="' . $size . '" height="' . $size . '" role="img" aria-label="Logo" style="display:block;flex:none;width:' . $size . 'px;height:' . $size . 'px">'
        . '<defs><linearGradient id="' . $id . '" x1="0.2" y1="0.05" x2="0.8" y2="0.95"><stop offset="0" stop-color="#ff967d"/><stop offset="0.68" stop-color="#e65b44"/><stop offset="1" stop-color="#ba4937"/></linearGradient></defs>'
        . '<path d="M15.6 0H32.4A15.6 15.6 0 0 1 48 15.6V32.4A15.6 15.6 0 0 1 32.4 48H6A6 6 0 0 1 0 42V15.6A15.6 15.6 0 0 1 15.6 0Z" fill="url(#' . $id . ')"/>'
        . '<g transform="translate(7.8 7.8) scale(0.675)" fill="none">'
        . '<path d="M15 9 7 13 3.5 25l7 3.5 3.3-6.1V40h20.4V22.4l3.3 6.1 7-3.5L41 13l-8-4-4.5 3.2a8 8 0 0 1-9 0L15 9Z" fill="rgba(255,255,255,.22)" stroke="#fff" stroke-width="2.1" stroke-linejoin="round"/>'
        . '<path d="M19.5 12.1a6 6 0 0 0 9 0M16 34h16" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>'
        . '<path d="m29 27 3 3-3 3" stroke="#ffe0a8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></g></svg>';
}
$admin = current_admin();
$admin_nav = $admin_nav ?? '';
$cssFile = __DIR__ . '/../assets/css/admin.css';
$cssVer = is_file($cssFile) ? filemtime($cssFile) : 1;
$favicon = 'data:image/svg+xml,' . rawurlencode(admin_logo(48));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($admin_title ?? 'Admin') ?> | Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?= (int)$cssVer ?>">
  <link rel="icon" type="image/svg+xml" href="<?= h($favicon) ?>">
</head>
<body class="<?= $admin ? 'admin-body' : 'admin-login-body' ?>">
<?php if ($admin): ?>
<header class="admin-bar">
  <a class="admin-brand" href="index.php" style="display:inline-flex;align-items:center;gap:10px"><?= admin_logo(36) ?><span>Admin</span></a>
  <nav class="admin-nav" aria-label="Admin">
    <a href="index.php" class="<?= $admin_nav === 'orders' ? 'active' : '' ?>">Orders</a>
    <a href="catalog.php" class="<?= $admin_nav === 'catalog' ? 'active' : '' ?>">Catalog &amp; prices</a>
    <a href="account.php" class="<?= $admin_nav === 'account' ? 'active' : '' ?>">Account</a>
    <a href="../index.php" target="_blank" rel="noopener">View site ↗</a>
  </nav>
  <div class="admin-user"><span><?= h($admin['username']) ?></span> <button type="button" class="btn btn-ghost js-logout">Sign out</button></div>
</header>
<?php endif; ?>
<div class="toast-container" aria-live="polite"></div>
