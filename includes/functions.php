<?php
require_once __DIR__ . '/config.php';

// ---------- session ----------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

// ---------- tiny helpers ----------
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    } catch (PDOException $e) {
        error_log('DB connect failed: ' . $e->getMessage());
        db_down_response();
    }
    return $pdo;
}

function db_down_response(): void {
    $isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
    http_response_code(503);
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Database unavailable. Start MySQL in XAMPP and import database.sql.']);
    } else {
        echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:560px;margin:60px auto;line-height:1.6">'
           . '<h2>Database not reachable</h2><p>1. Open the XAMPP Control Panel and make sure <b>MySQL</b> is started.<br>'
           . '2. Import <code>database.sql</code> in phpMyAdmin (creates <code>' . h(DB_NAME) . '</code>).<br>'
           . '3. Check the settings in <code>includes/config.php</code>.</p></body>';
    }
    exit;
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $code = 400, array $extra = []): void {
    json_out(['ok' => false, 'error' => $msg] + $extra, $code);
}

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function require_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    $known = $_SESSION['csrf'] ?? '';
    if ($known === '' || !is_string($sent) || !hash_equals($known, $sent)) {
        fail('Session expired. Please refresh the page and try again.', 419);
    }
}

/** For API endpoints: POST only + CSRF; returns decoded JSON body (or $_POST for multipart). */
function api_post(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('POST required.', 405);
    require_csrf();
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $d = json_decode(file_get_contents('php://input'), true);
        if (!is_array($d)) fail('Invalid request body.');
        return $d;
    }
    return $_POST;
}

// ---------- auth ----------
function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login_page(): array {
    $u = current_user();
    if (!$u) {
        $next = basename($_SERVER['SCRIPT_NAME']);
        header('Location: auth.php?next=' . urlencode($next));
        exit;
    }
    return $u;
}
function require_login_api(): array {
    $u = current_user();
    if (!$u) fail('Please sign in first.', 401);
    return $u;
}

// ---------- admin auth (separate from customer accounts) ----------
function current_admin(): ?array { return $_SESSION['admin'] ?? null; }

function require_admin_page(): array {
    $a = current_admin();
    if (!$a) { header('Location: login.php'); exit; }
    return $a;
}
function require_admin_api(): array {
    $a = current_admin();
    if (!$a) fail('Admin sign-in required.', 401);
    return $a;
}

// ---------- catalogue & pricing (single source of truth; the browser only displays it) ----------
const SHIRT_TYPES = ['tshirt' => 'T-Shirt', 'polo' => 'Polo', 'hoodie' => 'Hoodie', 'longsleeve' => 'Long Sleeve'];
const SHIRT_COLORS = [
    '#FFFFFF' => 'White', '#000000' => 'Black', '#FF0000' => 'Red', '#1E3A8A' => 'Navy Blue',
    '#2563EB' => 'Royal Blue', '#16A34A' => 'Forest Green', '#DC6800' => 'Orange', '#7E22CE' => 'Purple',
    '#DB2777' => 'Hot Pink', '#6B7280' => 'Gray', '#92400E' => 'Brown', '#0EA5E9' => 'Sky Blue',
];
const BASE_PRICE = ['XS' => 3000, 'S' => 3200, 'M' => 3500, 'L' => 3800, 'XL' => 4200, '2XL' => 4800, '3XL' => 5500];
const IMAGE_FEE = 500;   // per shirt, if any image/preset is used
const TEXT_FEE  = 300;   // per shirt, if any text is used
const QTY_DISCOUNT = [50 => 0.20, 20 => 0.15, 10 => 0.10, 5 => 0.05];   // highest first
const MAX_QTY = 500;
const FONTS = ['Poppins', 'Georgia', 'Impact', 'Courier New', 'Arial Black', 'Verdana'];

const ORDER_STATUSES = ['new' => 'New', 'processing' => 'Processing', 'printing' => 'Printing',
                        'ready' => 'Ready for delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
const PAYMENT_STATUSES = ['awaiting_payment' => 'Awaiting payment', 'paid' => 'Paid', 'pay_on_delivery' => 'Pay on delivery'];

/**
 * The catalogue above is only the built-in default. When the admin tables exist (database.sql /
 * database/catalog_admin.sql) the shop owner edits sizes, prices, colours, fees and discounts in the
 * admin panel and everything below reads those values. If the tables are missing, the defaults are used.
 */
function catalog_load(): ?array {
    static $c = false;
    if ($c !== false) return $c;
    try {
        $pdo = db();
        $c = [
            'types'     => $pdo->query('SELECT code, name, is_active FROM catalog_types ORDER BY sort_order, code')->fetchAll(),
            'sizes'     => $pdo->query('SELECT code, base_price, is_active FROM catalog_sizes ORDER BY sort_order, code')->fetchAll(),
            'colors'    => $pdo->query('SELECT hex, name, is_active FROM catalog_colors ORDER BY sort_order, hex')->fetchAll(),
            'discounts' => $pdo->query('SELECT min_qty, percent FROM catalog_discounts ORDER BY min_qty DESC')->fetchAll(),
            'settings'  => $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR),
        ];
    } catch (PDOException $e) {
        $c = null;
    }
    return $c;
}

/** code => label. $all = include items the admin has switched off (used to label old orders). */
function catalog_types(bool $all = false): array {
    $c = catalog_load(); $out = [];
    foreach (($c['types'] ?? []) as $r) if ($all || $r['is_active']) $out[$r['code']] = $r['name'];
    if ($out) return $out;
    return SHIRT_TYPES;
}
/** size => base price */
function catalog_sizes(bool $all = false): array {
    $c = catalog_load(); $out = [];
    foreach (($c['sizes'] ?? []) as $r) if ($all || $r['is_active']) $out[$r['code']] = (float)$r['base_price'];
    return $out ?: array_map('floatval', BASE_PRICE);
}
/** '#RRGGBB' => name */
function catalog_colors(bool $all = false): array {
    $c = catalog_load(); $out = [];
    foreach (($c['colors'] ?? []) as $r) if ($all || $r['is_active']) $out[strtoupper($r['hex'])] = $r['name'];
    return $out ?: SHIRT_COLORS;
}
function catalog_fees(): array {
    $s = catalog_load()['settings'] ?? [];
    return ['image' => isset($s['image_fee']) ? (float)$s['image_fee'] : (float)IMAGE_FEE,
            'text'  => isset($s['text_fee'])  ? (float)$s['text_fee']  : (float)TEXT_FEE];
}
/** min quantity => discount as a fraction (0.10 = 10%), highest quantity first */
function catalog_discounts(): array {
    $c = catalog_load();
    if ($c === null) return QTY_DISCOUNT;
    $out = [];
    foreach ($c['discounts'] as $r) $out[(int)$r['min_qty']] = (float)$r['percent'] / 100;
    krsort($out);
    return $out;
}
function type_label(string $code): string  { return catalog_types(true)[$code] ?? SHIRT_TYPES[$code] ?? $code; }
function color_label(string $hex): string  { return catalog_colors(true)[strtoupper($hex)] ?? SHIRT_COLORS[strtoupper($hex)] ?? $hex; }
function order_status_label(string $s): string { return ORDER_STATUSES[$s] ?? ucfirst($s); }

function price_quote(string $size, int $qty, bool $hasImage, bool $hasText): array {
    $sizes = catalog_sizes(true);
    $fees  = catalog_fees();
    $base  = $sizes[$size] ?? ($sizes['M'] ?? reset($sizes));
    $unit  = $base + ($hasImage ? $fees['image'] : 0) + ($hasText ? $fees['text'] : 0);
    $disc  = 0.0;
    foreach (catalog_discounts() as $min => $pct) { if ($qty >= $min) { $disc = $pct; break; } }
    $total = round($unit * $qty * (1 - $disc), 2);
    return ['unit' => $unit, 'discount' => $disc, 'total' => $total, 'qty' => $qty];
}

function naira(float $n): string { return '₦' . number_format($n, 2); }

// ---------- design validation ----------
function clean_num($v, float $min, float $max, float $def = 0): float {
    return is_numeric($v) ? max($min, min($max, (float)$v)) : $def;
}

/** Whitelist/clean one side's layers coming from the browser. Returns [layers, hasImage, hasText]. */
function clean_layers($layers): array {
    $out = []; $hasImage = false; $hasText = false;
    if (!is_array($layers)) return [[], false, false];
    foreach (array_slice($layers, 0, 40) as $l) {
        if (!is_array($l)) continue;
        $type = $l['type'] ?? '';
        $x = clean_num($l['x'] ?? 0, -2000, 4000);
        $y = clean_num($l['y'] ?? 0, -2000, 4000);
        if ($type === 'text') {
            $text = trim(mb_substr((string)($l['text'] ?? ''), 0, 120));
            if ($text === '') continue;
            $font = in_array($l['font'] ?? '', FONTS, true) ? $l['font'] : 'Poppins';
            $style = in_array($l['style'] ?? '', ['normal', 'bold', 'italic', 'italic bold'], true) ? $l['style'] : 'normal';
            $color = preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($l['color'] ?? '')) ? strtoupper($l['color']) : '#000000';
            $out[] = ['type' => 'text', 'text' => $text, 'font' => $font, 'style' => $style, 'color' => $color,
                      'size' => (int)clean_num($l['size'] ?? 32, 8, 200, 32), 'x' => $x, 'y' => $y];
            $hasText = true;
        } elseif ($type === 'image') {
            $src = (string)($l['src'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/', $src)) continue;
            if (!is_file(__DIR__ . '/../uploads/artwork/' . $src)) continue;
            $out[] = ['type' => 'image', 'src' => $src, 'x' => $x, 'y' => $y,
                      'width' => clean_num($l['width'] ?? 120, 10, 2000, 120), 'height' => clean_num($l['height'] ?? 120, 10, 2000, 120)];
            $hasImage = true;
        } elseif ($type === 'emoji') {
            $ch = mb_substr((string)($l['char'] ?? ''), 0, 8);
            if ($ch === '') continue;
            $out[] = ['type' => 'emoji', 'char' => $ch, 'size' => (int)clean_num($l['size'] ?? 80, 10, 400, 80), 'x' => $x, 'y' => $y];
            $hasImage = true;
        }
    }
    return [$out, $hasImage, $hasText];
}

/** Validate a posted shirt configuration. Returns a clean array or calls fail(). */
function clean_design(array $in): array {
    $type  = (string)($in['type'] ?? 'tshirt');
    $size  = (string)($in['size'] ?? 'M');
    $color = strtoupper((string)($in['color'] ?? '#FFFFFF'));
    $qty   = (int)($in['qty'] ?? 1);
    if (!isset(catalog_types()[$type]))   fail('That shirt type is not available.');
    if (!isset(catalog_sizes()[$size]))   fail('That size is not available.');
    // Catalog colours, or any custom #RRGGBB the customer picked with the colour wheel
    if (!isset(catalog_colors()[$color]) && !preg_match('/^#[0-9A-F]{6}$/', $color)) fail('That colour is not available.');
    if ($qty < 1 || $qty > MAX_QTY)  fail('Quantity must be between 1 and ' . MAX_QTY . '.');
    [$front, $fi, $ft] = clean_layers($in['layers']['front'] ?? []);
    [$back,  $bi, $bt] = clean_layers($in['layers']['back']  ?? []);
    return ['type' => $type, 'size' => $size, 'color' => $color, 'qty' => $qty,
            'layers' => ['front' => $front, 'back' => $back],
            'hasImage' => $fi || $bi, 'hasText' => $ft || $bt];
}

// ---------- images ----------
/** Save a "data:image/png;base64,..." string as a PNG in uploads/designs. Returns file name or null. */
function save_png_dataurl($dataUrl, int $maxBytes = 6 * 1024 * 1024): ?string {
    if (!is_string($dataUrl) || strpos($dataUrl, 'data:image/png;base64,') !== 0) return null;
    $bin = base64_decode(substr($dataUrl, 22), true);
    if ($bin === false || strlen($bin) > $maxBytes) return null;
    $info = @getimagesizefromstring($bin);
    if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 3000 || $info[1] > 3000) return null;
    $name = bin2hex(random_bytes(16)) . '.png';
    $dir = __DIR__ . '/../uploads/designs';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return file_put_contents($dir . '/' . $name, $bin) === false ? null : $name;
}

function status_label(string $s): string {
    return ['paid' => 'Paid', 'awaiting_payment' => 'Awaiting payment', 'pay_on_delivery' => 'Pay on delivery'][$s] ?? $s;
}
