<?php
// AJAX endpoint (admin): action = login | logout | change_password
require_once __DIR__ . '/../includes/functions.php';
$in = api_post();
$action = $in['action'] ?? '';

if ($action === 'login') {
    // light brute-force throttle (per browser session)
    $_SESSION['admin_fails'] = array_filter($_SESSION['admin_fails'] ?? [], fn($t) => $t > time() - 600);
    if (count($_SESSION['admin_fails']) >= 5) fail('Too many failed attempts. Please wait 10 minutes.', 429);

    $username = trim((string)($in['username'] ?? ''));
    $pass     = (string)($in['password'] ?? '');
    if ($username === '' || $pass === '') fail('Enter your username and password.');

    $st = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $a = $st->fetch();
    $hash = $a['password_hash'] ?? '$2y$10$usesomesillystringforsaltandthenhashgoeshere1234567890abcd';
    if (!password_verify($pass, $hash) || !$a) {
        $_SESSION['admin_fails'][] = time();
        fail('Invalid username or password.', 401);
    }
    $_SESSION['admin_fails'] = [];
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => (int)$a['id'], 'username' => $a['username']];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    json_out(['ok' => true, 'redirect' => 'index.php']);
}

if ($action === 'logout') {
    unset($_SESSION['admin']);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    json_out(['ok' => true, 'redirect' => 'login.php']);
}

if ($action === 'change_password') {
    $admin = require_admin_api();
    $current = (string)($in['current_password'] ?? '');
    $new     = (string)($in['new_password'] ?? '');
    $confirm = (string)($in['confirm_password'] ?? '');
    if (strlen($new) < 8) fail('The new password must be at least 8 characters.');
    if ($new !== $confirm) fail('The new passwords do not match.');
    $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $st->execute([$admin['id']]);
    $row = $st->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) fail('Your current password is incorrect.', 403);
    db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
    json_out(['ok' => true, 'message' => 'Password updated.']);
}

fail('Unknown action.');
