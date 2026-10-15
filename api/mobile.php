<?php
// Read-only JSON endpoint for the ShirtCraft mobile app (mobile/).
// The web pages render this data as HTML; the app asks for it here instead.
// Writes still go through the existing endpoints (auth.php, quote.php, upload.php, save_draft.php, place_order.php, admin_*.php),
// so all validation and pricing stays in one place.
// GET api/mobile.php?action = session | catalog | draft | my_orders | order | admin_orders | admin_order | admin_catalog
require_once __DIR__ . '/../includes/functions.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') fail('GET required.', 405);
header('Cache-Control: no-store');
$action = (string)($_GET['action'] ?? '');

function bank_details(): array {
    return ['name' => BANK_NAME, 'account' => BANK_ACCOUNT, 'holder' => BANK_HOLDER];
}

/** One order row as the app needs it (labels resolved on the server, like the web pages do). */
function order_json(array $o, bool $withLayers = false): array {
    $out = [
        'id' => (int)$o['id'], 'ref' => $o['order_ref'], 'created_at' => date('c', strtotime($o['created_at'])),
        'customer_name' => $o['customer_name'], 'customer_email' => $o['customer_email'], 'customer_phone' => $o['customer_phone'],
        'address' => $o['delivery_address'], 'city' => $o['delivery_city'], 'state' => $o['delivery_state'], 'notes' => $o['notes'],
        'type' => $o['shirt_type'], 'type_label' => type_label($o['shirt_type']),
        'size' => $o['shirt_size'], 'color' => $o['shirt_color'], 'color_label' => color_label($o['shirt_color']),
        'qty' => (int)$o['quantity'], 'unit_price' => (float)$o['unit_price'], 'discount_pct' => (float)$o['discount_pct'],
        'total' => (float)$o['total_amount'], 'payment_method' => $o['payment_method'],
        'payment_status' => $o['payment_status'], 'payment_label' => status_label($o['payment_status']),
        'order_status' => $o['order_status'], 'order_label' => order_status_label($o['order_status']),
        'design_front' => $o['design_front'] ? 'uploads/designs/' . $o['design_front'] : null,
        'design_back'  => $o['design_back']  ? 'uploads/designs/' . $o['design_back']  : null,
    ];
    if ($withLayers) {
        $d = json_decode((string)$o['design_data'], true);
        $out['layers'] = ['front' => is_array($d['front'] ?? null) ? $d['front'] : [], 'back' => is_array($d['back'] ?? null) ? $d['back'] : []];
    }
    return $out;
}

function pairs(array $map, string $k, string $v): array {
    $out = [];
    foreach ($map as $key => $val) $out[] = [$k => (string)$key, $v => $val];
    return $out;
}

if ($action === 'session') {
    $u = current_user(); $a = current_admin();
    json_out(['ok' => true, 'csrf' => csrf_token(), 'user' => $u, 'admin' => $a]);
}

if ($action === 'catalog') {
    $sizes = catalog_sizes(); $colors = catalog_colors();
    $discounts = [];
    foreach (catalog_discounts() as $min => $pct) $discounts[] = ['min_qty' => (int)$min, 'percent' => round($pct * 100, 2)];
    json_out(['ok' => true,
        'types' => pairs(catalog_types(), 'code', 'name'),
        'sizes' => pairs($sizes, 'code', 'price'),
        'colors' => pairs($colors, 'hex', 'name'),
        'default_size' => isset($sizes['M']) ? 'M' : (string)array_key_first($sizes),
        'default_color' => isset($colors['#FFFFFF']) ? '#FFFFFF' : (string)array_key_first($colors),
        'discounts' => $discounts, 'fees' => catalog_fees(), 'fonts' => FONTS, 'max_qty' => MAX_QTY,
        'max_upload_bytes' => MAX_UPLOAD_BYTES, 'bank' => bank_details()]);
}

if ($action === 'draft') {
    $user = require_login_api();
    $d = $_SESSION['draft'] ?? null;
    if (!$d) json_out(['ok' => true, 'draft' => null]);
    $q = price_quote($d['size'], $d['qty'], $d['hasImage'], $d['hasText']);
    json_out(['ok' => true, 'user' => $user, 'bank' => bank_details(), 'draft' => [
        'type' => $d['type'], 'type_label' => type_label($d['type']), 'size' => $d['size'],
        'color' => $d['color'], 'color_label' => color_label($d['color']), 'qty' => $d['qty'],
        'front' => $d['front'] ? 'uploads/designs/' . $d['front'] : null,
        'back'  => $d['back']  ? 'uploads/designs/' . $d['back']  : null,
    ], 'quote' => $q + ['subtotal' => $q['unit'] * $q['qty']]]);
}

if ($action === 'my_orders') {
    $user = require_login_api();
    $st = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
    $st->execute([$user['id']]);
    json_out(['ok' => true, 'orders' => array_map('order_json', $st->fetchAll())]);
}

if ($action === 'order') {
    $user = require_login_api();
    $st = db()->prepare('SELECT * FROM orders WHERE order_ref = ? AND user_id = ? LIMIT 1');   // only the owner can view it
    $st->execute([(string)($_GET['ref'] ?? ''), $user['id']]);
    $o = $st->fetch();
    if (!$o) fail('We could not find an order with that reference on your account.', 404);
    json_out(['ok' => true, 'order' => order_json($o), 'bank' => bank_details()]);
}

// ---------------------------------------------------------------- admin
if ($action === 'admin_orders') {
    require_admin_api();
    $fStatus = (string)($_GET['status'] ?? ''); $fPay = (string)($_GET['pay'] ?? ''); $q = trim((string)($_GET['q'] ?? ''));
    $where = []; $args = [];
    if (isset(ORDER_STATUSES[$fStatus])) { $where[] = 'order_status = ?';   $args[] = $fStatus; }
    if (isset(PAYMENT_STATUSES[$fPay]))  { $where[] = 'payment_status = ?'; $args[] = $fPay; }
    if ($q !== '') {
        $where[] = '(order_ref LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        array_push($args, $like, $like, $like, $like);
    }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $perPage = 20;
    $st = db()->prepare('SELECT COUNT(*) FROM orders' . $sqlWhere);
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = max(1, min($pages, (int)($_GET['page'] ?? 1)));
    $st = db()->prepare('SELECT * FROM orders' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
    $st->execute($args);
    $stats = db()->query("SELECT COUNT(*) AS n,
            SUM(payment_status = 'awaiting_payment' AND order_status <> 'cancelled') AS awaiting,
            SUM(order_status IN ('processing','printing')) AS in_production,
            SUM(order_status = 'new') AS new_orders,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount END), 0) AS paid_total
        FROM orders")->fetch();
    json_out(['ok' => true, 'orders' => array_map('order_json', $st->fetchAll()),
        'page' => $page, 'pages' => $pages, 'total' => $total,
        'stats' => ['all' => (int)$stats['n'], 'new' => (int)$stats['new_orders'], 'in_production' => (int)$stats['in_production'],
                    'awaiting' => (int)$stats['awaiting'], 'paid_total' => (float)$stats['paid_total']],
        'order_statuses' => pairs(ORDER_STATUSES, 'code', 'label'), 'payment_statuses' => pairs(PAYMENT_STATUSES, 'code', 'label')]);
}

if ($action === 'admin_order') {
    require_admin_api();
    $st = db()->prepare('SELECT * FROM orders WHERE order_ref = ? LIMIT 1');
    $st->execute([(string)($_GET['ref'] ?? '')]);
    $o = $st->fetch();
    if (!$o) fail('Order not found.', 404);
    json_out(['ok' => true, 'order' => order_json($o, true),
        'order_statuses' => pairs(ORDER_STATUSES, 'code', 'label'), 'payment_statuses' => pairs(PAYMENT_STATUSES, 'code', 'label')]);
}

if ($action === 'admin_catalog') {
    require_admin_api();
    try {
        $pdo = db();
        json_out(['ok' => true,
            'types'  => $pdo->query('SELECT code, name, is_active FROM catalog_types ORDER BY sort_order, code')->fetchAll(),
            'sizes'  => $pdo->query('SELECT code, base_price, is_active FROM catalog_sizes ORDER BY sort_order, code')->fetchAll(),
            'colors' => $pdo->query('SELECT hex, name, is_active FROM catalog_colors ORDER BY sort_order, hex')->fetchAll(),
            'discounts' => $pdo->query('SELECT min_qty, percent FROM catalog_discounts ORDER BY min_qty')->fetchAll(),
            'fees' => catalog_fees()]);
    } catch (PDOException $e) {
        fail('The catalogue tables are missing. Import database/catalog_admin.sql.', 500);
    }
}

fail('Unknown action.');
