<?php
// AJAX endpoint: turn the session draft + delivery details into an order row.
require_once __DIR__ . '/../includes/functions.php';
$in = api_post();
$user = require_login_api();
$d = $_SESSION['draft'] ?? null;
if (!$d) fail('Your design session expired. Please open the Design Studio and add your design to the order again.', 409);

$name  = trim((string)($in['fullname'] ?? ''));
$email = trim((string)($in['email'] ?? ''));
$phone = trim((string)($in['phone'] ?? ''));
$addr  = trim((string)($in['address'] ?? ''));
$city  = trim((string)($in['city'] ?? ''));
$state = trim((string)($in['state'] ?? ''));
$notes = trim((string)($in['notes'] ?? ''));
$pay   = (string)($in['payment_method'] ?? 'bank');

if ($name === '' || $phone === '' || $addr === '' || $city === '' || $state === '') fail('Please fill in all delivery details.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
if (!in_array($pay, ['bank', 'pod'], true)) fail('Choose a payment method.');
if (mb_strlen($name) > 150 || mb_strlen($addr) > 255 || mb_strlen($city) > 100 || mb_strlen($state) > 100 || mb_strlen($notes) > 255 || mb_strlen($phone) > 50) {
    fail('One of the fields is too long.');
}

// Recompute the price on the server — never trust a total coming from the browser.
$q = price_quote($d['size'], $d['qty'], $d['hasImage'], $d['hasText']);
$status = $pay === 'pod' ? 'pay_on_delivery' : 'awaiting_payment';

$sql = 'INSERT INTO orders (order_ref, user_id, customer_name, customer_email, customer_phone,
          delivery_address, delivery_city, delivery_state, notes, shirt_type, shirt_size, shirt_color,
          quantity, unit_price, discount_pct, total_amount, payment_method, payment_status,
          design_front, design_back, design_data)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
$ref = null;
for ($try = 0; $try < 5 && !$ref; $try++) {
    $cand = 'SCA-' . strtoupper(bin2hex(random_bytes(4)));
    try {
        db()->prepare($sql)->execute([
            $cand, $user['id'], $name, $email, $phone, $addr, $city, $state, $notes ?: null,
            $d['type'], $d['size'], $d['color'], $d['qty'], $q['unit'], $q['discount'] * 100, $q['total'],
            $pay, $status, $d['front'], $d['back'], json_encode($d['layers'], JSON_UNESCAPED_UNICODE),
        ]);
        $ref = $cand;
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') throw $e;     // only retry on duplicate reference
    }
}
if (!$ref) fail('Could not create the order. Please try again.', 500);

unset($_SESSION['draft']);
json_out(['ok' => true, 'redirect' => 'receipt.php?ref=' . urlencode($ref)]);
