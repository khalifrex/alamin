<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login_page();

$d = $_SESSION['draft'] ?? null;
if (!$d) { header('Location: designer.php'); exit; }

$q = price_quote($d['size'], $d['qty'], $d['hasImage'], $d['hasText']);
$colorName = color_label($d['color']);

$page_title = 'Checkout';
$extra_css = ['assets/css/auth.css'];
$extra_js = ['assets/js/checkout.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="checkout-page">
  <div style="max-width:1100px;margin:0 auto 24px">
    <h1 style="font-size:1.6rem;font-weight:800;color:var(--primary)">🛒 Checkout</h1>
    <p style="color:var(--gray)">Review your order and enter your delivery details</p>
  </div>

  <div id="checkout-alert" style="display:none;max-width:1100px;margin:0 auto 20px;background:#fee2e2;border:1px solid #ef4444;color:#b91c1c;padding:12px 16px;border-radius:8px"></div>

  <form id="checkout-form" class="checkout-grid" novalidate>
    <input type="hidden" name="payment_method" id="payment-method" value="bank">

    <div class="checkout-main">

      <div class="checkout-card">
        <h3>📍 Delivery Information</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label for="checkout-name">Full Name</label>
            <input type="text" id="checkout-name" name="fullname" value="<?= h($user['fullname']) ?>" required>
          </div>
          <div class="form-group">
            <label for="checkout-email">Email Address</label>
            <input type="email" id="checkout-email" name="email" value="<?= h($user['email']) ?>" required>
          </div>
          <div class="form-group">
            <label for="checkout-phone">Phone Number</label>
            <input type="tel" id="checkout-phone" name="phone" value="<?= h($user['phone']) ?>" required>
          </div>
          <div class="form-group">
            <label for="checkout-state">State</label>
            <select id="checkout-state" name="state" required>
              <option value="">Select State</option>
              <?php foreach (['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT Abuja','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara'] as $st): ?>
                <option<?= $st === 'Lagos' ? ' selected' : '' ?>><?= h($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label for="checkout-address">Delivery Address</label>
            <input type="text" id="checkout-address" name="address" placeholder="House/Street number and street name" required>
          </div>
          <div class="form-group">
            <label for="checkout-city">City / Town</label>
            <input type="text" id="checkout-city" name="city" placeholder="e.g. Ikeja" required>
          </div>
          <div class="form-group">
            <label for="checkout-notes">Additional Notes (optional)</label>
            <input type="text" id="checkout-notes" name="notes" placeholder="e.g. Landmark, special instructions" maxlength="255">
          </div>
        </div>
      </div>

      <div class="checkout-card">
        <h3>💳 Payment Method</h3>
        <div class="payment-options">
          <div class="payment-option selected" data-method="bank">
            <div class="pay-icon">🏦</div><h4>Bank Transfer</h4><p>Transfer to our <?= h(BANK_NAME) ?> account</p>
          </div>
          <div class="payment-option" data-method="pod">
            <div class="pay-icon">🚚</div><h4>Pay on Delivery</h4><p>Pay when your order arrives</p>
          </div>
        </div>

        <div id="bank-transfer-details">
          <div class="bank-details">
            <div style="font-weight:700;color:var(--primary);margin-bottom:14px;font-size:0.9rem">🏦 Transfer to this account:</div>
            <div class="bank-detail-row"><span class="bank-detail-label">Bank Name</span><span class="bank-detail-value"><?= h(BANK_NAME) ?></span></div>
            <div class="bank-detail-row"><span class="bank-detail-label">Account Number</span>
              <span class="bank-detail-value"><?= h(BANK_ACCOUNT) ?>
                <button type="button" class="copy-btn" data-copy="<?= h(BANK_ACCOUNT) ?>">Copy</button></span></div>
            <div class="bank-detail-row"><span class="bank-detail-label">Account Name</span><span class="bank-detail-value"><?= h(BANK_HOLDER) ?></span></div>
            <div class="bank-detail-row"><span class="bank-detail-label">Amount to Transfer</span><span class="bank-detail-value" style="color:var(--accent)"><?= h(naira($q['total'])) ?></span></div>
          </div>
          <div style="background:rgba(240,165,0,0.1);border:1px solid rgba(240,165,0,0.3);border-radius:8px;padding:14px;margin-top:14px;font-size:0.82rem;color:var(--dark-gray);line-height:1.7">
            ⚠️ <strong>Important:</strong> Click "Place Order" first — you'll get an <strong>Order Reference</strong> to use as your payment narration.
            Production starts once your transfer is confirmed.
          </div>
        </div>

        <div id="pod-details" style="display:none;background:rgba(39,174,96,0.08);border:1px solid rgba(39,174,96,0.2);border-radius:8px;padding:14px;font-size:0.85rem;line-height:1.7">
          🚚 You'll pay <strong><?= h(naira($q['total'])) ?></strong> in cash or by transfer to the rider when your order is delivered.
        </div>
      </div>
    </div>

    <div>
      <div class="order-summary-card">
        <h3>📋 Order Summary</h3>
        <div class="order-item">
          <img src="uploads/designs/<?= h($d['front']) ?>" alt="Your design (front)" style="width:70px;height:84px;object-fit:contain;border-radius:8px;background:#f8f8f8;border:1px solid #eee">
          <div class="order-item-info">
            <div class="order-item-name">Custom <?= h(type_label($d['type'])) ?></div>
            <div class="order-item-meta">Size: <?= h($d['size']) ?> · Color: <?= h($colorName) ?>
              <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= h($d['color']) ?>;border:1px solid #ddd;vertical-align:middle;margin-left:4px"></span></div>
            <div class="order-item-meta">Qty: <?= (int)$d['qty'] ?> × <?= h(naira($q['unit'])) ?></div>
            <?php if ($d['back']): ?><div class="order-item-meta">Includes back print</div><?php endif; ?>
          </div>
        </div>
        <div class="order-totals">
          <div class="total-row"><span>Subtotal</span><span><?= h(naira($q['unit'] * $q['qty'])) ?></span></div>
          <?php if ($q['discount'] > 0): ?>
          <div class="total-row" style="color:var(--success)"><span>Bulk discount (<?= (int)round($q['discount'] * 100) ?>%)</span><span>−<?= h(naira($q['unit'] * $q['qty'] - $q['total'])) ?></span></div>
          <?php endif; ?>
          <div class="total-row"><span>Delivery</span><span>₦0.00</span></div>
          <div class="total-row grand"><span>Total</span><span><?= h(naira($q['total'])) ?></span></div>
        </div>
        <button type="submit" class="btn-place-order" id="btn-place-order">✅ Place Order Now</button>
        <div style="font-size:0.75rem;color:var(--gray);text-align:center;margin-top:12px;line-height:1.7">
          <a href="designer.php" style="color:var(--accent);font-weight:600">← Edit design</a>
        </div>
      </div>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
