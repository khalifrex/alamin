<?php
// AJAX endpoint: live price quote (the browser asks, PHP answers — prices live only in PHP)
require_once __DIR__ . '/../includes/functions.php';
$in = api_post();
$size = (string)($in['size'] ?? 'M');
$qty  = (int)($in['qty'] ?? 1);
if (!isset(catalog_sizes()[$size])) fail('That size is not available.');
if ($qty < 1 || $qty > MAX_QTY) fail('Quantity must be between 1 and ' . MAX_QTY . '.');
$q = price_quote($size, $qty, !empty($in['hasImage']), !empty($in['hasText']));
json_out(['ok' => true, 'unit' => $q['unit'], 'discount' => $q['discount'], 'total' => $q['total'],
          'unit_fmt' => naira($q['unit']), 'total_fmt' => naira($q['total'])]);
