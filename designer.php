<?php
require_once __DIR__ . '/includes/functions.php';
// What the shop currently sells (edited by the admin under Admin > Catalog).
$types  = catalog_types();
$sizes  = array_map('strval', array_keys(catalog_sizes()));
$colors = catalog_colors();
$defSize  = in_array('M', $sizes, true) ? 'M' : $sizes[0];
$defColor = isset($colors['#FFFFFF']) ? '#FFFFFF' : array_key_first($colors);
$discounts = catalog_discounts();
$page_title = 'Design Studio | Live Shirt Preview';
$extra_css = ['assets/css/designer.css'];
$extra_js  = ['assets/vendor/three.min.js', 'assets/vendor/OrbitControls.js', 'assets/js/designer.js'];
require __DIR__ . '/includes/header.php';
?>

<!-- ===== DESIGNER LAYOUT ===== -->
<div class="designer-layout">

  <!-- ===== LEFT PANEL ===== -->
  <div class="designer-left">

    <!-- Shirt Type -->
    <div class="panel-section">
      <h3>Shirt Type</h3>
      <div class="shirt-types">
        <?php $icons = ['tshirt' => '👕', 'polo' => '👔', 'hoodie' => '🧥', 'longsleeve' => '🧤']; $first = true;
        foreach ($types as $k => $label): ?>
          <button type="button" class="shirt-type-btn<?= $first ? ' active' : '' ?>" data-type="<?= h($k) ?>">
            <span class="type-icon"><?= $icons[$k] ?? '👕' ?></span><?= h($label) ?>
          </button>
        <?php $first = false; endforeach; ?>
      </div>
    </div>

    <!-- Size -->
    <div class="panel-section">
      <h3>Size</h3>
      <div class="size-grid">
        <?php foreach ($sizes as $s): ?>
          <button type="button" class="size-btn<?= $s === $defSize ? ' active' : '' ?>" data-size="<?= h($s) ?>"><?= h($s) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Color -->
    <div class="panel-section">
      <h3>Shirt Color</h3>
      <div class="color-grid">
        <?php foreach ($colors as $hex => $name): ?>
          <div class="color-swatch<?= $hex === $defColor ? ' active' : '' ?>" data-color="<?= h($hex) ?>" data-name="<?= h($name) ?>" title="<?= h($name) ?>"
               style="background:<?= h($hex) ?><?= $hex === '#FFFFFF' ? ';border-color:#eee' : '' ?>"></div>
        <?php endforeach; ?>

        <!-- Custom colour picker: any colour the customer wants -->
        <div class="color-swatch" id="custom-swatch" data-color="#FF6600" data-name="Custom" title="Pick any colour"
             style="position:relative;overflow:hidden;background:conic-gradient(red,yellow,lime,cyan,blue,magenta,red)">
          <input type="color" id="custom-color-input" value="#FF6600"
                 style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;border:0;padding:0">
        </div>
      </div>
      <div class="color-name" id="color-name-display"><?= h($colors[$defColor]) ?></div>
    </div>

    <!-- Quantity -->
    <div class="panel-section">
      <h3>Quantity</h3>
      <div class="qty-control">
        <button type="button" class="qty-btn" id="qty-minus">−</button>
        <span class="qty-display" id="qty-display">1</span>
        <button type="button" class="qty-btn" id="qty-plus">+</button>
      </div>
      <?php if ($discounts): ?><p style="font-size:0.75rem;color:var(--gray);margin-top:8px">🎁 <?= (int)min(array_keys($discounts)) ?>+ pieces get a discount!</p><?php endif; ?>
    </div>

    <!-- Price -->
    <div class="price-display" id="price-display">
      <div class="price-label">Total Price</div>
      <div class="price-amount" id="price-amount">…</div>
      <div class="price-per" id="price-per">for 1 item</div>
      <span id="discount-badge" style="display:none;background:var(--success);color:#fff;padding:3px 10px;border-radius:50px;font-size:0.75rem;font-weight:700;margin-top:6px">5% off!</span>
    </div>

    <!-- Add to Order -->
    <button type="button" class="btn-add-order" id="btn-add-order">
      🛒 Add to Order & Checkout
    </button>

  </div>

  <!-- ===== CENTER CANVAS ===== -->
  <div class="designer-center">
    <div class="canvas-header">
      <h2>🎨 Design Canvas</h2>
      <div class="view-toggle">
        <button type="button" class="view-btn active" data-view="front">Front</button>
        <button type="button" class="view-btn" data-view="back">Back</button>
        <button type="button" class="view-btn" data-view="3d">🌀 3D View</button>
      </div>
    </div>

    <div id="canvas-wrapper">
      <canvas id="shirt-canvas"></canvas>
      <div class="canvas-overlay">
        <div class="canvas-hint">Click or drag to interact with design elements</div>
      </div>
    </div>

    <div class="canvas-controls">
      <button type="button" class="ctrl-btn" id="ctrl-delete">🗑️ Delete Selected</button>
      <button type="button" class="ctrl-btn" id="ctrl-clear">🧹 Clear All</button>
      <button type="button" class="ctrl-btn" id="ctrl-preview-3d">🌀 3D Preview</button>
    </div>

    <p class="rotate-hint" id="rotate-hint" style="visibility:hidden">
      <span>💡</span> In 3D view: drag to rotate · scroll to zoom
    </p>
  </div>

  <!-- ===== RIGHT PANEL ===== -->
  <div class="designer-right">

    <!-- Tabs -->
    <div class="design-tabs">
      <button type="button" class="design-tab active" data-tab="tab-upload">📤 Upload</button>
      <button type="button" class="design-tab" data-tab="tab-text">🔤 Text</button>
      <button type="button" class="design-tab" data-tab="tab-presets">⭐ Presets</button>
      <button type="button" class="design-tab" data-tab="tab-layers">📋 Layers</button>
    </div>

    <!-- Upload Tab -->
    <div class="tab-content active" id="tab-upload">
      <div class="upload-zone">
        <input type="file" id="design-upload" accept="image/png,image/jpeg,image/gif,image/webp">
        <div class="upload-icon">📁</div>
        <h4>Upload Your Design</h4>
        <p>Drag & drop or click to browse</p>
        <div class="file-types">
          <span class="file-type-badge">PNG</span>
          <span class="file-type-badge">JPG</span>
          <span class="file-type-badge">GIF</span>
          <span class="file-type-badge">WEBP</span>
        </div>
      </div>
      <p style="font-size:0.75rem;color:var(--gray);margin-top:12px;text-align:center">
        ✅ Max file size: 10MB<br>
        For best results, use high resolution (300 DPI+)
      </p>
      <div style="margin-top:16px;padding:14px;background:var(--light-gray);border-radius:var(--radius-sm)">
        <p style="font-size:0.8rem;font-weight:700;color:var(--primary);margin-bottom:6px">💡 Design Tips</p>
        <ul style="font-size:0.75rem;color:var(--gray);padding-left:16px;line-height:1.8">
          <li>Use transparent PNG for best results</li>
          <li>Minimum 2000×2000px recommended</li>
          <li>Drag design to reposition after upload</li>
        </ul>
      </div>
    </div>

    <!-- Text Tab -->
    <div class="tab-content" id="tab-text">
      <div class="text-tool-form">
        <div class="form-group">
          <label for="text-input">Your Text</label>
          <textarea id="text-input" placeholder="Type anything you want on your shirt..." rows="3"></textarea>
        </div>

        <div>
          <label style="font-size:0.8rem;font-weight:700;color:var(--gray);display:block;margin-bottom:8px">FONT FAMILY</label>
          <div class="font-grid">
            <button type="button" class="font-btn active" data-font="Poppins" style="font-family:Poppins">Poppins</button>
            <button type="button" class="font-btn" data-font="Georgia" style="font-family:Georgia">Georgia</button>
            <button type="button" class="font-btn" data-font="Impact" style="font-family:Impact">Impact</button>
            <button type="button" class="font-btn" data-font="Courier New" style="font-family:'Courier New'">Courier</button>
            <button type="button" class="font-btn" data-font="Arial Black" style="font-family:'Arial Black'">Arial Black</button>
            <button type="button" class="font-btn" data-font="Verdana" style="font-family:Verdana">Verdana</button>
          </div>
        </div>

        <div class="form-group">
          <label for="text-size">Font Size: <span id="size-label">32</span>px</label>
          <input type="range" id="text-size" min="16" max="120" value="32"
            style="width:100%">
        </div>

        <div>
          <label style="font-size:0.8rem;font-weight:700;color:var(--gray);display:block;margin-bottom:8px">STYLE</label>
          <div class="style-row">
            <button type="button" class="style-btn" data-style="bold"><b>Bold</b></button>
            <button type="button" class="style-btn" data-style="italic"><i>Italic</i></button>
          </div>
        </div>

        <div>
          <label style="font-size:0.8rem;font-weight:700;color:var(--gray);display:block;margin-bottom:8px">TEXT COLOR</label>
          <div class="color-row">
            <div class="quick-color active" data-color="#000000" style="background:#000000"></div>
            <div class="quick-color" data-color="#FFFFFF" style="background:#FFFFFF;border:2px solid #ddd"></div>
            <div class="quick-color" data-color="#FF0000" style="background:#FF0000"></div>
            <div class="quick-color" data-color="#FFD700" style="background:#FFD700"></div>
            <div class="quick-color" data-color="#2563EB" style="background:#2563EB"></div>
            <div class="quick-color" data-color="#16A34A" style="background:#16A34A"></div>
            <div class="quick-color" data-color="#e94560" style="background:#e94560"></div>
            <input type="color" id="text-color-picker" value="#000000"
              style="width:28px;height:28px;padding:2px;border:2px solid #ddd;border-radius:50%;cursor:pointer">
          </div>
        </div>

        <button type="button" class="btn-add-text" id="btn-add-text">+ Add Text to Shirt</button>
      </div>
    </div>

    <!-- Presets Tab -->
    <div class="tab-content" id="tab-presets">
      <p style="font-size:0.8rem;color:var(--gray);margin-bottom:14px">Click a preset to add it to your design</p>
      <div class="presets-grid">
        <div class="preset-card">
          <div class="preset-preview">🦁</div>
          <div class="preset-name">Lion</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">⭐</div>
          <div class="preset-name">Star</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">🔥</div>
          <div class="preset-name">Fire</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">💎</div>
          <div class="preset-name">Diamond</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">🎯</div>
          <div class="preset-name">Target</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">🌟</div>
          <div class="preset-name">Glow Star</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">👑</div>
          <div class="preset-name">Crown</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">⚡</div>
          <div class="preset-name">Lightning</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">🏆</div>
          <div class="preset-name">Trophy</div>
        </div>
        <div class="preset-card">
          <div class="preset-preview">🌍</div>
          <div class="preset-name">Globe</div>
        </div>
      </div>
    </div>

    <!-- Layers Tab -->
    <div class="tab-content" id="tab-layers">
      <p style="font-size:0.8rem;color:var(--gray);margin-bottom:14px">All your design layers</p>
      <div id="layers-list">
        <div class="empty-layers">
          <span>📋</span>No design layers yet.<br>Add text or upload an image.
        </div>
      </div>
    </div>

  </div>
</div>


<script>
(function () {
  var input  = document.getElementById('custom-color-input');
  var swatch = document.getElementById('custom-swatch');
  if (!input || !swatch) return;
  input.addEventListener('input', function () {
    var hex = input.value.toUpperCase();
    swatch.setAttribute('data-color', hex);
    swatch.setAttribute('data-name', hex);
    swatch.title = hex;
    swatch.style.background = hex;
    swatch.click();   // reuse the existing swatch handler in designer.js
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
