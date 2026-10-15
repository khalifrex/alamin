<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login_page();

$st = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$st->execute([$user['id']]);
$orders = $st->fetchAll();

$page_title = 'My Orders';
require __DIR__ . '/includes/header.php';
?>

<div style="min-height:calc(100vh - 70px);background:var(--light-gray);padding:50px 5%">
  <div style="max-width:960px;margin:0 auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:16px">
      <div>
        <h1 style="font-size:1.8rem;font-weight:800;color:var(--primary)">📦 My Orders</h1>
        <p style="color:var(--gray)">Track and view all your custom clothing orders</p>
      </div>
      <a href="designer.php" class="btn-primary" style="padding:10px 22px;font-size:0.9rem">+ Design New Apparel</a>
    </div>

    <?php if (!$orders): ?>
      <div style="background:#fff;border-radius:var(--radius);padding:60px 20px;text-align:center;box-shadow:var(--shadow)">
        <div style="font-size:3.5rem;margin-bottom:16px">🛍️</div>
        <h2 style="font-size:1.4rem;color:var(--primary);margin-bottom:8px">No Orders Placed Yet</h2>
        <p style="color:var(--gray);margin-bottom:24px">Design your first custom shirt and see it in 3D before you order.</p>
        <a href="designer.php" class="btn-primary">🎨 Open Design Studio</a>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:16px">
      <?php foreach ($orders as $o): $paid = $o['payment_status'] === 'paid'; ?>
        <div style="background:#fff;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px">
          <div style="display:flex;gap:20px;align-items:center">
            <?php if ($o['design_front']): ?>
              <img src="uploads/designs/<?= h($o['design_front']) ?>" alt="" style="width:70px;height:84px;object-fit:contain;border-radius:8px;border:1px solid #ddd;background:#f8f8f8">
            <?php else: ?>
              <div style="width:70px;height:70px;border-radius:8px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:2rem">👕</div>
            <?php endif; ?>
            <div>
              <div style="font-weight:800;font-size:1.1rem;color:var(--primary)"><?= h($o['order_ref']) ?></div>
              <div style="font-size:0.85rem;color:var(--gray);margin-top:2px"><?= h(type_label($o['shirt_type'])) ?> · Size <?= h($o['shirt_size']) ?> · Qty: <?= (int)$o['quantity'] ?></div>
              <div style="font-size:0.78rem;color:#888;margin-top:4px">Placed on <?= h(date('M d, Y', strtotime($o['created_at']))) ?></div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
            <div style="font-size:1.25rem;font-weight:800;color:var(--accent)"><?= h(naira((float)$o['total_amount'])) ?></div>
            <span style="font-size:0.75rem;padding:4px 10px;border-radius:20px;font-weight:700;<?= $paid ? 'background:#dcfce7;color:#15803d' : 'background:#fef3c7;color:#b45309' ?>">
              <?= $paid ? '✅ ' : '⏳ ' ?><?= h(status_label($o['payment_status'])) ?>
            </span>
            <span style="font-size:0.75rem;padding:4px 10px;border-radius:20px;font-weight:700;background:#e0e7ff;color:#3730a3">🧵 <?= h(order_status_label($o['order_status'])) ?></span>
            <a href="receipt.php?ref=<?= urlencode($o['order_ref']) ?>" style="font-size:0.85rem;color:var(--accent);font-weight:600;margin-top:4px">View Receipt →</a>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
