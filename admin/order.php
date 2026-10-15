<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_page();

$ref = (string)($_GET['ref'] ?? '');
$st = db()->prepare('SELECT * FROM orders WHERE order_ref = ? LIMIT 1');
$st->execute([$ref]);
$o = $st->fetch() ?: null;

$layers = ['front' => [], 'back' => []];
if ($o) {
    $decoded = json_decode((string)$o['design_data'], true);
    if (is_array($decoded)) {
        $layers['front'] = is_array($decoded['front'] ?? null) ? $decoded['front'] : [];
        $layers['back']  = is_array($decoded['back'] ?? null)  ? $decoded['back']  : [];
    }
}

$admin_title = $o ? 'Order ' . $o['order_ref'] : 'Order not found';
$admin_nav = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<main class="admin-main">
<?php if (!$o): ?>
  <div class="empty-card"><div class="empty-icon">🔎</div><h2>Order not found</h2><p class="muted">No order has the reference “<?= h($ref) ?>”.</p><a class="btn btn-primary" href="index.php">Back to orders</a></div>
<?php else: ?>
  <div class="page-head">
    <div>
      <p class="crumb"><a href="index.php">← All orders</a></p>
      <h1><?= h($o['order_ref']) ?></h1>
      <p class="muted">Placed <?= h(date('M d, Y · h:i A', strtotime($o['created_at']))) ?></p>
    </div>
    <div class="head-actions no-print"><button type="button" class="btn btn-ghost" onclick="window.print()">🖨️ Print job sheet</button></div>
  </div>

  <div class="detail-grid">
    <section class="card no-print-border">
      <h2>Status</h2>
      <div class="status-row">
        <label>Payment
          <select class="js-order-field pill pay-<?= h($o['payment_status']) ?>" data-field="payment_status" data-id="<?= (int)$o['id'] ?>">
            <?php foreach (PAYMENT_STATUSES as $k => $label): ?><option value="<?= h($k) ?>" <?= $o['payment_status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Production
          <select class="js-order-field pill st-<?= h($o['order_status']) ?>" data-field="order_status" data-id="<?= (int)$o['id'] ?>">
            <?php foreach (ORDER_STATUSES as $k => $label): ?><option value="<?= h($k) ?>" <?= $o['order_status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <p class="muted small">Customers see the production status on their receipt and in “My Orders”.</p>
    </section>

    <section class="card">
      <h2>Item</h2>
      <dl class="facts">
        <dt>Shirt type</dt><dd><?= h(type_label($o['shirt_type'])) ?></dd>
        <dt>Size</dt><dd><?= h($o['shirt_size']) ?></dd>
        <dt>Colour</dt><dd><span class="dot" style="background:<?= h($o['shirt_color']) ?>"></span><?= h(color_label($o['shirt_color'])) ?> <span class="muted small"><?= h($o['shirt_color']) ?></span></dd>
        <dt>Quantity</dt><dd><?= (int)$o['quantity'] ?> × <?= h(naira((float)$o['unit_price'])) ?></dd>
        <?php if ((float)$o['discount_pct'] > 0): ?><dt>Bulk discount</dt><dd><?= h(rtrim(rtrim($o['discount_pct'], '0'), '.')) ?>%</dd><?php endif; ?>
        <dt>Total</dt><dd><strong><?= h(naira((float)$o['total_amount'])) ?></strong></dd>
        <dt>Payment method</dt><dd><?= $o['payment_method'] === 'pod' ? 'Pay on delivery' : 'Bank transfer' ?></dd>
      </dl>
    </section>

    <section class="card">
      <h2>Customer &amp; delivery</h2>
      <dl class="facts">
        <dt>Name</dt><dd><?= h($o['customer_name']) ?></dd>
        <dt>Phone</dt><dd><a href="tel:<?= h(preg_replace('/\s+/', '', $o['customer_phone'])) ?>"><?= h($o['customer_phone']) ?></a></dd>
        <dt>Email</dt><dd><a href="mailto:<?= h($o['customer_email']) ?>"><?= h($o['customer_email']) ?></a></dd>
        <dt>Address</dt><dd><?= h($o['delivery_address']) ?>, <?= h($o['delivery_city']) ?>, <?= h($o['delivery_state']) ?></dd>
        <?php if (!empty($o['notes'])): ?><dt>Notes</dt><dd><?= h($o['notes']) ?></dd><?php endif; ?>
      </dl>
    </section>
  </div>

  <section class="card">
    <h2>Design</h2>
    <div class="design-previews">
      <?php foreach (['front' => 'design_front', 'back' => 'design_back'] as $side => $col): if (empty($o[$col])): continue; endif; ?>
        <figure>
          <img src="../uploads/designs/<?= h($o[$col]) ?>" alt="<?= ucfirst($side) ?> design">
          <figcaption><?= ucfirst($side) ?> · <a href="../uploads/designs/<?= h($o[$col]) ?>" download class="no-print">Download PNG</a></figcaption>
        </figure>
      <?php endforeach; ?>
      <?php if (empty($o['design_front']) && empty($o['design_back'])): ?><p class="muted">No preview image was saved for this order.</p><?php endif; ?>
    </div>

    <?php foreach (['front' => 'Front', 'back' => 'Back'] as $side => $label): if (!$layers[$side]) continue; ?>
      <h3><?= $label ?> print layers</h3>
      <div class="table-wrap"><table class="admin-table compact">
        <thead><tr><th>#</th><th>Type</th><th>Details</th><th>Position</th></tr></thead>
        <tbody>
        <?php foreach ($layers[$side] as $i => $l): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= h(ucfirst($l['type'] ?? '')) ?></td>
            <td>
              <?php if (($l['type'] ?? '') === 'text'): ?>
                “<?= h($l['text'] ?? '') ?>” — <?= h($l['font'] ?? '') ?>, <?= (int)($l['size'] ?? 0) ?>px, <?= h($l['style'] ?? 'normal') ?>
                <span class="dot" style="background:<?= h($l['color'] ?? '#000000') ?>"></span><?= h($l['color'] ?? '') ?>
              <?php elseif (($l['type'] ?? '') === 'image'): ?>
                <a href="../uploads/artwork/<?= h($l['src'] ?? '') ?>" target="_blank" rel="noopener"><?= h($l['src'] ?? '') ?></a> — <?= (int)($l['width'] ?? 0) ?> × <?= (int)($l['height'] ?? 0) ?> px
              <?php elseif (($l['type'] ?? '') === 'emoji'): ?>
                <span class="emoji"><?= h($l['char'] ?? '') ?></span> preset, <?= (int)($l['size'] ?? 0) ?>px
              <?php endif; ?>
            </td>
            <td class="muted">x <?= (int)($l['x'] ?? 0) ?>, y <?= (int)($l['y'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
