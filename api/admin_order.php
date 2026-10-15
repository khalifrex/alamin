<?php
// AJAX endpoint (admin): update an order's production status and/or payment status.
require_once __DIR__ . '/../includes/functions.php';
$in = api_post();
require_admin_api();

$id = (int)($in['id'] ?? 0);
if ($id < 1) fail('Unknown order.');

$sets = []; $args = [];
if (isset($in['order_status'])) {
    if (!isset(ORDER_STATUSES[$in['order_status']])) fail('Invalid order status.');
    $sets[] = 'order_status = ?'; $args[] = $in['order_status'];
}
if (isset($in['payment_status'])) {
    if (!isset(PAYMENT_STATUSES[$in['payment_status']])) fail('Invalid payment status.');
    $sets[] = 'payment_status = ?'; $args[] = $in['payment_status'];
}
if (!$sets) fail('Nothing to update.');

$args[] = $id;
$st = db()->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = ?');
$st->execute($args);

$chk = db()->prepare('SELECT order_status, payment_status FROM orders WHERE id = ?');
$chk->execute([$id]);
$row = $chk->fetch();
if (!$row) fail('Order not found.', 404);
json_out(['ok' => true, 'order_status' => $row['order_status'], 'payment_status' => $row['payment_status'],
          'order_label' => order_status_label($row['order_status']), 'payment_label' => status_label($row['payment_status'])]);
