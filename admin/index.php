<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_page();

// ---- filters ----
$fStatus = (string)($_GET['status'] ?? '');
$fPay    = (string)($_GET['pay'] ?? '');
$q       = trim((string)($_GET['q'] ?? ''));
$where = []; $args = [];
if (isset(ORDER_STATUSES[$fStatus]))   { $where[] = 'order_status = ?';   $args[] = $fStatus; } else { $fStatus = ''; }
if (isset(PAYMENT_STATUSES[$fPay]))    { $where[] = 'payment_status = ?'; $args[] = $fPay; }    else { $fPay = ''; }
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
$offset = ($page - 1) * $perPage;

$st = db()->prepare('SELECT * FROM orders' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$st->execute($args);
$orders = $st->fetchAll();

// ---- headline numbers (whole shop, not just the filtered list) ----
$stats = db()->query("SELECT COUNT(*) AS n,
        SUM(payment_status = 'awaiting_payment' AND order_status <> 'cancelled') AS awaiting,
        SUM(order_status IN ('processing','printing')) AS in_production,
        SUM(order_status = 'new') AS new_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount END), 0) AS paid_total
    FROM orders")->fetch();

function page_url(array $over = []): string {
    $params = array_merge(['status' => $_GET['status'] ?? '', 'pay' => $_GET['pay'] ?? '', 'q' => $_GET['q'] ?? '', 'page' => 1], $over);
    return 'index.php?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 1));
}

$admin_title = 'Orders'; $admin_nav = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<main class="admin-main">
  <div class="page-head">
    <div><h1>Orders</h1><p class="muted">Review customer orders, confirm payments and move them through production.</p></div>
  </div>

  <section class="stat-grid" aria-label="Summary">
    <div class="stat"><span>All orders</span><strong><?= (int)$stats['n'] ?></strong></div>
    <div class="stat"><span>New</span><strong><?= (int)$stats['new_orders'] ?></strong></div>
    <div class="stat"><span>In production</span><strong><?= (int)$stats['in_production'] ?></strong></div>
    <div class="stat"><span>Awaiting payment</span><strong><?= (int)$stats['awaiting'] ?></strong></div>
    <div class="stat"><span>Paid so far</span><strong><?= h(naira((float)$stats['paid_total'])) ?></strong></div>
  </section>

  <form class="filters" method="get" action="index.php">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search reference, name, email or phone">
    <select name="status" aria-label="Order status">
      <option value="">All order statuses</option>
      <?php foreach (ORDER_STATUSES as $k => $label): ?><option value="<?= h($k) ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
    <select name="pay" aria-label="Payment status">
      <option value="">All payments</option>
      <?php foreach (PAYMENT_STATUSES as $k => $label): ?><option value="<?= h($k) ?>" <?= $fPay === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($q !== '' || $fStatus !== '' || $fPay !== ''): ?><a class="btn btn-ghost" href="index.php">Clear</a><?php endif; ?>
  </form>

  <?php if (!$orders): ?>
    <div class="empty-card"><div class="empty-icon">📭</div><h2>No orders found</h2><p class="muted"><?= $total === 0 && !$where ? 'Orders appear here as soon as customers check out.' : 'Try a different filter or search.' ?></p></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>Order</th><th>Customer</th><th>Item</th><th class="num">Total</th><th>Payment</th><th>Production</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr data-id="<?= (int)$o['id'] ?>">
          <td data-label="Order">
            <div class="order-cell">
              <?php if ($o['design_front']): ?><img src="../uploads/designs/<?= h($o['design_front']) ?>" alt="" class="thumb"><?php else: ?><span class="thumb thumb-empty">👕</span><?php endif; ?>
              <div><a href="order.php?ref=<?= urlencode($o['order_ref']) ?>"><strong><?= h($o['order_ref']) ?></strong></a><div class="muted small"><?= h(date('M d, Y · H:i', strtotime($o['created_at']))) ?></div></div>
            </div>
          </td>
          <td data-label="Customer"><strong><?= h($o['customer_name']) ?></strong><div class="muted small"><?= h($o['customer_phone']) ?></div></td>
          <td data-label="Item">
            <?= h(type_label($o['shirt_type'])) ?> · <?= h($o['shirt_size']) ?> × <?= (int)$o['quantity'] ?>
            <div class="muted small"><span class="dot" style="background:<?= h($o['shirt_color']) ?>"></span><?= h(color_label($o['shirt_color'])) ?></div>
          </td>
          <td data-label="Total" class="num"><strong><?= h(naira((float)$o['total_amount'])) ?></strong></td>
          <td data-label="Payment">
            <select class="js-order-field pill pay-<?= h($o['payment_status']) ?>" data-field="payment_status" data-id="<?= (int)$o['id'] ?>" aria-label="Payment status for <?= h($o['order_ref']) ?>">
              <?php foreach (PAYMENT_STATUSES as $k => $label): ?><option value="<?= h($k) ?>" <?= $o['payment_status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </td>
          <td data-label="Production">
            <select class="js-order-field pill st-<?= h($o['order_status']) ?>" data-field="order_status" data-id="<?= (int)$o['id'] ?>" aria-label="Production status for <?= h($o['order_ref']) ?>">
              <?php foreach (ORDER_STATUSES as $k => $label): ?><option value="<?= h($k) ?>" <?= $o['order_status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select>
          </td>
          <td data-label=""><a class="btn btn-ghost" href="order.php?ref=<?= urlencode($o['order_ref']) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-ghost" href="<?= h(page_url(['page' => $page - 1])) ?>">← Newer</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?> · <?= $total ?> orders</span>
    <?php if ($page < $pages): ?><a class="btn btn-ghost" href="<?= h(page_url(['page' => $page + 1])) ?>">Older →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
