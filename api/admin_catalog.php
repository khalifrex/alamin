<?php
// AJAX endpoint (admin): edit the catalogue the Design Studio sells.
// action = save_type | save_size | delete_size | save_color | delete_color | save_fees | save_discounts
require_once __DIR__ . '/../includes/functions.php';
$in = api_post();
require_admin_api();
$action = (string)($in['action'] ?? '');
$pdo = db();

function money($v, string $what): float {
    if (!is_numeric($v) || (float)$v < 0 || (float)$v > 100000000) fail('Enter a valid ' . $what . ' (zero or more).');
    return round((float)$v, 2);
}
/** The shop must always have something to sell: roll back if a change would leave a list empty. */
function keep_one(PDO $pdo, string $table, string $label): void {
    if ((int)$pdo->query("SELECT COUNT(*) FROM `$table` WHERE is_active = 1")->fetchColumn() < 1) {
        $pdo->rollBack();
        fail('At least one ' . $label . ' must stay available.');
    }
}

try {
    $pdo->beginTransaction();

    if ($action === 'save_type') {
        $code = (string)($in['code'] ?? '');
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 50) { $pdo->rollBack(); fail('Enter a shirt type name (max 50 characters).'); }
        $st = $pdo->prepare('UPDATE catalog_types SET name = ?, is_active = ? WHERE code = ?');
        $st->execute([$name, empty($in['is_active']) ? 0 : 1, $code]);
        keep_one($pdo, 'catalog_types', 'shirt type');
        $msg = 'Shirt type saved.';

    } elseif ($action === 'save_size') {
        $code = strtoupper(trim((string)($in['code'] ?? '')));
        if (!preg_match('/^[A-Z0-9-]{1,10}$/D', $code)) { $pdo->rollBack(); fail('Size labels use up to 10 letters, numbers or dashes (e.g. XL, 2XL).'); }
        $price = money($in['base_price'] ?? null, 'price');
        $active = empty($in['is_active']) ? 0 : 1;
        $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM catalog_sizes')->fetchColumn();
        $pdo->prepare('INSERT INTO catalog_sizes (code, base_price, sort_order, is_active) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), is_active = VALUES(is_active)')
            ->execute([$code, $price, $order, $active]);
        keep_one($pdo, 'catalog_sizes', 'size');
        $msg = 'Size ' . $code . ' saved.';

    } elseif ($action === 'delete_size') {
        $pdo->prepare('DELETE FROM catalog_sizes WHERE code = ?')->execute([strtoupper((string)($in['code'] ?? ''))]);
        keep_one($pdo, 'catalog_sizes', 'size');
        $msg = 'Size deleted. Old orders keep their size.';

    } elseif ($action === 'save_color') {
        $hex = strtoupper(trim((string)($in['hex'] ?? '')));
        $name = trim((string)($in['name'] ?? ''));
        if (!preg_match('/^#[0-9A-F]{6}$/D', $hex)) { $pdo->rollBack(); fail('Pick a valid colour.'); }
        if ($name === '' || mb_strlen($name) > 50) { $pdo->rollBack(); fail('Enter a colour name (max 50 characters).'); }
        $active = empty($in['is_active']) ? 0 : 1;
        $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM catalog_colors')->fetchColumn();
        $pdo->prepare('INSERT INTO catalog_colors (hex, name, sort_order, is_active) VALUES (?,?,?,?)
                       ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active)')
            ->execute([$hex, $name, $order, $active]);
        keep_one($pdo, 'catalog_colors', 'colour');
        $msg = $name . ' saved.';

    } elseif ($action === 'delete_color') {
        $pdo->prepare('DELETE FROM catalog_colors WHERE hex = ?')->execute([strtoupper((string)($in['hex'] ?? ''))]);
        keep_one($pdo, 'catalog_colors', 'colour');
        $msg = 'Colour deleted. Old orders keep their colour.';

    } elseif ($action === 'save_fees') {
        $img = money($in['image_fee'] ?? null, 'image fee');
        $txt = money($in['text_fee'] ?? null, 'text fee');
        $st = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $st->execute(['image_fee', (string)$img]);
        $st->execute(['text_fee', (string)$txt]);
        $msg = 'Print fees saved.';

    } elseif ($action === 'save_discounts') {
        $rows = $in['rows'] ?? [];
        if (!is_array($rows) || count($rows) > 12) { $pdo->rollBack(); fail('Add up to 12 discount tiers.'); }
        $clean = [];
        foreach ($rows as $r) {
            $q = $r['min_qty'] ?? null; $p = $r['percent'] ?? null;
            if (!is_numeric($q) || (int)$q < 2 || (int)$q > MAX_QTY) { $pdo->rollBack(); fail('Minimum quantity must be between 2 and ' . MAX_QTY . '.'); }
            if (!is_numeric($p) || (float)$p <= 0 || (float)$p > 90) { $pdo->rollBack(); fail('Discount must be more than 0% and at most 90%.'); }
            $clean[(int)$q] = round((float)$p, 2);          // same quantity twice => last one wins
        }
        $pdo->exec('DELETE FROM catalog_discounts');
        $st = $pdo->prepare('INSERT INTO catalog_discounts (min_qty, percent) VALUES (?,?)');
        foreach ($clean as $q => $p) $st->execute([$q, $p]);
        $msg = 'Bulk discounts saved.';

    } else {
        $pdo->rollBack();
        fail('Unknown action.');
    }

    $pdo->commit();
    json_out(['ok' => true, 'message' => $msg]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('admin_catalog: ' . $e->getMessage());
    fail('Could not save. Have you imported database/catalog_admin.sql?', 500);
}
