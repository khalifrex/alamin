<?php
require_once __DIR__ . '/functions.php';
$current_user = current_user();
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
  <title><?= isset($page_title) ? h($page_title) . ' | ShirtCraft' : 'ShirtCraft | Custom Apparel' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <?php foreach (($extra_css ?? []) as $css): ?>
  <link rel="stylesheet" href="<?= h($css) ?>">
  <?php endforeach; ?>
  <link rel="icon" type="image/svg+xml" href="assets/images/shirtcraft-icon.svg">
</head>
<body>

<div id="loading-screen">
  <div class="loader-logo"><img src="assets/images/shirtcraft-logo.svg" alt="ShirtCraft — Wear your own story"></div>
  <div class="loader-bar"></div>
</div>

<nav>
  <a href="index.php" class="nav-logo" aria-label="ShirtCraft home"><img src="assets/images/shirtcraft-logo.svg" alt="ShirtCraft — Wear your own story"></a>

  <ul class="nav-links">
    <li><a href="index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a></li>
    <li><a href="designer.php" class="<?= $current_page === 'designer.php' ? 'active' : '' ?>">Design Studio</a></li>
    <li><a href="index.php#how-it-works">How It Works</a></li>
    <li><a href="index.php#features">Features</a></li>
    <li><a href="index.php#testimonials">Reviews</a></li>
    <li><a href="index.php#contact">Contact</a></li>
  </ul>

  <div class="nav-actions">
    <?php if ($current_user): ?>
      <div class="user-avatar-nav" id="nav-user-avatar">
        <span><?= h(strtoupper(mb_substr($current_user['fullname'], 0, 1))) ?></span>
        <div class="user-dropdown" id="user-dropdown">
          <div style="padding:12px 16px;border-bottom:1px solid #eee;font-size:0.85rem">
            <strong style="color:var(--primary);display:block"><?= h($current_user['fullname']) ?></strong>
            <span style="color:var(--gray);font-size:0.75rem"><?= h($current_user['email']) ?></span>
          </div>
          <a href="designer.php">🎨 Design Studio</a>
          <a href="my_orders.php">📦 My Orders</a>
          <a href="#" id="nav-logout">🚪 Sign Out</a>
        </div>
      </div>
    <?php else: ?>
      <a href="auth.php" class="btn-nav-login">Sign In</a>
      <a href="designer.php" class="btn-nav-start">Design Now</a>
    <?php endif; ?>

    <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
  </div>
</nav>

<div class="toast-container"></div>
