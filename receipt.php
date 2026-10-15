<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login_page();

$ref = (string)($_GET['ref'] ?? '');
$order = null;
if ($ref !== '') {
    $st = db()->prepare('SELECT * FROM orders WHERE order_ref = ? AND user_id = ? LIMIT 1');   // only the owner can view it
    $st->execute([$ref, $user['id']]);
    $order = $st->fetch() ?: null;
}

$page_title = 'Order Receipt';
$extra_css = ['assets/css/auth.css'];
require __DIR__ . '/includes/header.php';
?>

<div class="receipt-page">
  <div class="receipt-container">

  <?php if (!$order): ?>
    <div style="background:#fff;border-radius:12px;padding:60px 20px;text-align:center;box-shadow:var(--shadow)">
      <div style="font-size:3rem;margin-bottom:14px">📭</div>
      <h2>No Order Found</h2>
      <p style="color:var(--gray);margin:10px 0 20px">We could not find an order with that reference on your account.</p>
      <a href="designer.php" class="btn-primary" style="display:inline-block">🎨 Create a New Shirt Design</a>
    </div>
  <?php else: ?>
    <div class="receipt-card">
      <div class="receipt-header">
        <div class="receipt-success-icon">✅</div>
        <h2>Order Confirmed!</h2>
        <p>Thank you for your order. We're preparing your custom apparel.</p>
      </div>

      <div class="receipt-body">
        <div class="receipt-order-id"><span>Order Reference Number</span><strong><?= h($order['order_ref']) ?></strong></div>

        <div style="text-align:center;margin-bottom:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
          <?php foreach (['design_front' => 'Front', 'design_back' => 'Back'] as $col => $label): if (empty($order[$col])) continue; ?>
            <figure style="margin:0">
              <img src="uploads/designs/<?= h($order[$col]) ?>" alt="<?= $label ?> design" style="width:170px;height:204px;object-fit:contain;border-radius:12px;border:2px solid var(--border);background:#f8f8f8;box-shadow:0 4px 12px rgba(0,0,0,0.08)">
              <figcaption style="font-size:.75rem;color:var(--gray);margin-top:4px"><?= $label ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>

        <div class="receipt-section">
          <h4>📦 Order Details</h4>
          <div class="receipt-row"><span>Shirt Type</span><span><?= h(SHIRT_TYPES[$order['shirt_type']] ?? $order['shirt_type']) ?></span></div>
          <div class="receipt-row"><span>Size</span><span><?= h($order['shirt_size']) ?></span></div>
          <div class="receipt-row"><span>Color</span><span><?= h(SHIRT_COLORS[$order['shirt_color']] ?? $order['shirt_color']) ?>
            <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?= h($order['shirt_color']) ?>;border:1px solid #ddd;vertical-align:middle;margin-left:4px"></span></span></div>
          <div class="receipt-row"><span>Quantity</span><span><?= (int)$order['quantity'] ?> piece(s) × <?= h(naira((float)$order['unit_price'])) ?></span></div>
          <?php if ((float)$order['discount_pct'] > 0): ?>
          <div class="receipt-row"><span>Bulk Discount</span><span><?= h(rtrim(rtrim($order['discount_pct'], '0'), '.')) ?>%</span></div>
          <?php endif; ?>
          <div class="receipt-row"><span>Order Date</span><span><?= h(date('M d, Y - h:i A', strtotime($order['created_at']))) ?></span></div>
        </div>

        <div class="receipt-section">
          <h4>📍 Delivery Details</h4>
          <div class="receipt-row"><span>Name</span><span><?= h($order['customer_name']) ?></span></div>
          <div class="receipt-row"><span>Email</span><span><?= h($order['customer_email']) ?></span></div>
          <div class="receipt-row"><span>Phone</span><span><?= h($order['customer_phone']) ?></span></div>
          <div class="receipt-row"><span>Address</span><span style="text-align:right;max-width:260px"><?= h($order['delivery_address'] . ', ' . $order['delivery_city'] . ', ' . $order['delivery_state']) ?></span></div>
          <?php if (!empty($order['notes'])): ?><div class="receipt-row"><span>Notes</span><span style="text-align:right;max-width:260px"><?= h($order['notes']) ?></span></div><?php endif; ?>
        </div>

        <div class="receipt-section">
          <h4>💳 Payment Details</h4>
          <div class="receipt-row"><span>Payment Method</span><span><?= $order['payment_method'] === 'pod' ? '🚚 Pay on Delivery' : '🏦 Bank Transfer' ?></span></div>
          <div class="receipt-row"><span>Payment Status</span>
            <span style="color:<?= $order['payment_status'] === 'paid' ? 'var(--success)' : 'var(--warning)' ?>;font-weight:700">
              <?= $order['payment_status'] === 'paid' ? '✅ ' : '⏳ ' ?><?= h(status_label($order['payment_status'])) ?></span></div>
        </div>

        <div class="receipt-total-band"><span>Total Amount</span><strong><?= h(naira((float)$order['total_amount'])) ?></strong></div>

        <?php if ($order['payment_method'] === 'bank'): ?>
        <div class="receipt-bank-note">
          <h4>⚠️ Complete Your Bank Transfer</h4>
          <p>
            To confirm and begin production, please transfer the exact amount to:<br><br>
            <strong>Bank:</strong> <?= h(BANK_NAME) ?><br>
            <strong>Account Number:</strong> <?= h(BANK_ACCOUNT) ?><br>
            <strong>Account Name:</strong> <?= h(BANK_HOLDER) ?><br><br>
            Use your reference number <strong><?= h($order['order_ref']) ?></strong> as the payment narration.
          </p>
        </div>
        <?php endif; ?>

        <div class="receipt-actions">
          <button type="button" class="btn-receipt-print" onclick="window.print()">🖨️ Print Receipt</button>
          <a href="designer.php" class="btn-receipt-home" style="display:flex;align-items:center;justify-content:center">🎨 Design Another</a>
        </div>
        <div style="margin-top:10px">
          <a href="my_orders.php" class="btn-receipt-home" style="width:100%;background:var(--primary);display:block;text-align:center">📦 View My Orders</a>
        </div>
      </div>

      <div style="background:var(--primary);padding:16px 28px;text-align:center">
        <p style="color:rgba(255,255,255,0.6);font-size:0.8rem">© <?= date('Y') ?> ShirtCraft</p>
      </div>
    </div>
  <?php endif; ?>

  </div>
</div>

<style>
@media print {
  nav, footer, #loading-screen, .receipt-actions, .toast-container { display: none !important; }
  body { background: white !important; }
  .receipt-page { padding: 0 !important; background: white !important; }
  .receipt-card { box-shadow: none !important; border: 1px solid #ccc; }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
