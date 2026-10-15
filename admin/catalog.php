<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_page();

$tablesReady = true;
try {
    $types  = db()->query('SELECT * FROM catalog_types ORDER BY sort_order, code')->fetchAll();
    $sizes  = db()->query('SELECT * FROM catalog_sizes ORDER BY sort_order, code')->fetchAll();
    $colors = db()->query('SELECT * FROM catalog_colors ORDER BY sort_order, hex')->fetchAll();
    $discounts = db()->query('SELECT * FROM catalog_discounts ORDER BY min_qty')->fetchAll();
} catch (PDOException $e) {
    $tablesReady = false;
}
$fees = catalog_fees();

$admin_title = 'Catalog & prices'; $admin_nav = 'catalog';
require __DIR__ . '/../includes/admin_header.php';
?>
<main class="admin-main">
  <div class="page-head"><div><h1>Catalog &amp; prices</h1><p class="muted">Everything here is what customers see in the Design Studio. Changes are live immediately.</p></div></div>

<?php if (!$tablesReady): ?>
  <div class="alert alert-error">The catalogue tables are missing. In phpMyAdmin, select <code><?= h(DB_NAME) ?></code> and import <code>database/catalog_admin.sql</code>, then reload this page.</div>
<?php else: ?>

  <section class="card">
    <h2>Shirt types</h2>
    <p class="muted small">The four shapes are built into the Design Studio, so you can rename or switch them on and off here.</p>
    <div class="table-wrap"><table class="admin-table compact">
      <thead><tr><th>Type</th><th>Display name</th><th>Available</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($types as $t): ?>
        <tr data-code="<?= h($t['code']) ?>">
          <td class="muted"><?= h($t['code']) ?></td>
          <td><input type="text" class="f-name" value="<?= h($t['name']) ?>" maxlength="50" aria-label="Display name for <?= h($t['code']) ?>"></td>
          <td><label class="switch"><input type="checkbox" class="f-active" <?= $t['is_active'] ? 'checked' : '' ?>><span>Available</span></label></td>
          <td><button type="button" class="btn btn-primary js-save-type">Save</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="card">
    <h2>Sizes &amp; base prices</h2>
    <p class="muted small">The price of one plain shirt in that size, in naira. Print fees are added on top.</p>
    <div class="table-wrap"><table class="admin-table compact">
      <thead><tr><th>Size</th><th>Base price (₦)</th><th>Available</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($sizes as $s): ?>
        <tr data-code="<?= h($s['code']) ?>">
          <td><strong><?= h($s['code']) ?></strong></td>
          <td><input type="number" class="f-price" value="<?= h($s['base_price']) ?>" min="0" step="0.01" aria-label="Price for size <?= h($s['code']) ?>"></td>
          <td><label class="switch"><input type="checkbox" class="f-active" <?= $s['is_active'] ? 'checked' : '' ?>><span>Available</span></label></td>
          <td class="row-actions"><button type="button" class="btn btn-primary js-save-size">Save</button> <button type="button" class="btn btn-danger js-delete-size">Delete</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <form class="inline-form js-add-size" novalidate>
      <strong>Add a size</strong>
      <input type="text" name="code" placeholder="e.g. 4XL" maxlength="10" required aria-label="New size label">
      <input type="number" name="base_price" placeholder="Base price ₦" min="0" step="0.01" required aria-label="New size price">
      <button type="submit" class="btn btn-primary">Add size</button>
    </form>
  </section>

  <section class="card">
    <h2>Shirt colours</h2>
    <div class="color-admin-grid">
      <?php foreach ($colors as $c): ?>
        <div class="color-admin" data-hex="<?= h($c['hex']) ?>">
          <span class="swatch-lg" style="background:<?= h($c['hex']) ?>"></span>
          <input type="text" class="f-name" value="<?= h($c['name']) ?>" maxlength="50" aria-label="Name for <?= h($c['hex']) ?>">
          <span class="muted small"><?= h($c['hex']) ?></span>
          <label class="switch"><input type="checkbox" class="f-active" <?= $c['is_active'] ? 'checked' : '' ?>><span>Available</span></label>
          <div class="row-actions"><button type="button" class="btn btn-primary js-save-color">Save</button> <button type="button" class="btn btn-danger js-delete-color">Delete</button></div>
        </div>
      <?php endforeach; ?>
    </div>
    <form class="inline-form js-add-color" novalidate>
      <strong>Add a colour</strong>
      <input type="text" name="name" placeholder="e.g. Forest Green" maxlength="50" required aria-label="New colour name">
      <input type="color" name="hex" value="#2F6B4F" aria-label="New colour swatch">
      <button type="submit" class="btn btn-primary">Add colour</button>
    </form>
  </section>

  <div class="two-col">
    <section class="card">
      <h2>Print fees</h2>
      <p class="muted small">Added per shirt when the design uses an image or preset, or text.</p>
      <form id="fees-form" class="stack-form" novalidate>
        <label>Image / preset fee (₦)<input type="number" name="image_fee" value="<?= h($fees['image']) ?>" min="0" step="0.01" required></label>
        <label>Text fee (₦)<input type="number" name="text_fee" value="<?= h($fees['text']) ?>" min="0" step="0.01" required></label>
        <button type="submit" class="btn btn-primary">Save fees</button>
      </form>
    </section>

    <section class="card">
      <h2>Bulk discounts</h2>
      <p class="muted small">The best matching tier applies to the whole order. Leave empty to offer no discount.</p>
      <form id="discounts-form" class="stack-form" novalidate>
        <div id="discount-rows">
          <?php foreach ($discounts as $d): ?>
            <div class="discount-row"><input type="number" class="d-qty" value="<?= (int)$d['min_qty'] ?>" min="2" aria-label="Minimum quantity"> <span>or more pieces →</span> <input type="number" class="d-pct" value="<?= h(rtrim(rtrim($d['percent'], '0'), '.')) ?>" min="0.01" max="90" step="0.01" aria-label="Percent off"> <span>% off</span> <button type="button" class="btn btn-danger js-remove-tier" aria-label="Remove tier">✕</button></div>
          <?php endforeach; ?>
        </div>
        <div class="row-actions"><button type="button" class="btn btn-ghost" id="add-tier">+ Add tier</button> <button type="submit" class="btn btn-primary">Save discounts</button></div>
      </form>
    </section>
  </div>
<?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
