<?php
// AJAX endpoint: action = login | register | logout
require_once __DIR__ . '/../includes/functions.php';
$in = api_post();
$action = $in['action'] ?? '';

function safe_next($n): string {
    $allowed = ['designer.php', 'checkout.php', 'my_orders.php', 'index.php'];
    return in_array($n, $allowed, true) ? $n : 'designer.php';
}

function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$u['id'], 'fullname' => $u['fullname'], 'email' => $u['email'], 'phone' => $u['phone']];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($action === 'login') {
    // light brute-force throttle (per browser session)
    $_SESSION['fails'] = array_filter($_SESSION['fails'] ?? [], fn($t) => $t > time() - 600);
    if (count($_SESSION['fails']) >= 5) fail('Too many failed attempts. Please wait 10 minutes.', 429);

    $email = strtolower(trim((string)($in['email'] ?? '')));
    $pass  = (string)($in['password'] ?? '');
    if ($email === '' || $pass === '') fail('Enter your email and password.');

    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();
    // verify against a dummy hash when the user doesn't exist (keeps timing similar)
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltandthenhashgoeshere1234567890abcd';
    if (!password_verify($pass, $hash) || !$u) {
        $_SESSION['fails'][] = time();
        fail('Invalid email or password.', 401);
    }
    $_SESSION['fails'] = [];
    $draft = $_SESSION['draft'] ?? null;
    login_user($u);
    if ($draft) $_SESSION['draft'] = $draft;      // keep an in-progress design across sign-in
    json_out(['ok' => true, 'redirect' => safe_next($in['next'] ?? '')]);
}

if ($action === 'register') {
    $fullname = trim(preg_replace('/\s+/', ' ', (string)($in['fullname'] ?? '')));
    $email    = strtolower(trim((string)($in['email'] ?? '')));
    $code     = (string)($in['country_code'] ?? '+234');
    $phoneRaw = preg_replace('/[^\d]/', '', (string)($in['phone'] ?? ''));
    $pass     = (string)($in['password'] ?? '');
    $confirm  = (string)($in['confirm_password'] ?? '');

    if (mb_strlen($fullname) < 3 || strpos($fullname, ' ') === false) fail('Please enter your first and last name.');
    if (mb_strlen($fullname) > 150) fail('Name is too long.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) fail('Please enter a valid email address.');
    if (!in_array($code, ['+234', '+1', '+44', '+233', '+254'], true)) $code = '+234';
    if (strlen($phoneRaw) < 7 || strlen($phoneRaw) > 14) fail('A valid phone number is required.');
    if (strlen($pass) < 8) fail('Password must be at least 8 characters.');
    if ($pass !== $confirm) fail('Passwords do not match.');

    $phone = $code . ' ' . $phoneRaw;
    try {
        $st = db()->prepare('INSERT INTO users (fullname, email, phone, password_hash) VALUES (?,?,?,?)');
        $st->execute([$fullname, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') fail('An account with this email already exists. Please sign in.', 409);
        throw $e;
    }
    $id = (int)db()->lastInsertId();
    $draft = $_SESSION['draft'] ?? null;
    login_user(['id' => $id, 'fullname' => $fullname, 'email' => $email, 'phone' => $phone]);
    if ($draft) $_SESSION['draft'] = $draft;
    json_out(['ok' => true, 'redirect' => safe_next($in['next'] ?? '')]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true, 'redirect' => 'index.php']);
}

fail('Unknown action.');
