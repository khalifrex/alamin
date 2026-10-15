// ===== Stallion Design Studio — jQuery live preview =====
// 2D canvas editor (drag, resize, text, uploads, presets) + 3D preview (Three.js)
// Prices come from PHP (api/quote.php); this file only displays them.
(function ($, THREE) {
  'use strict';

  var W = 500, H = 600, CX = W / 2;

  // ------------------------------------------------------------------ state
  var state = {
    type: 'tshirt', size: 'M', color: '#FFFFFF', qty: 1,
    side: 'front',                       // front | back
    mode: '2d',                          // 2d | 3d
    layers: { front: [], back: [] },
    selected: -1
  };
  var MAX_QTY = 500;

  function curLayers() { return state.layers[state.side]; }
  function selLayer() { return state.selected >= 0 ? curLayers()[state.selected] : null; }

  // ------------------------------------------------------------------ shirt outlines
  // segments: ['M',x,y] ['L',x,y] ['Q',cx,cy,x,y]  — shared by the 2D canvas and the 3D shape
  function outline(type, side) {
    var long_ = (type === 'longsleeve' || type === 'hoodie');
    var bw = type === 'hoodie' ? 134 : 125;               // half body width
    var neckY = side === 'back' ? 62 : (type === 'hoodie' ? 78 : 100);
    var s = [['M', CX - 46, 38], ['L', CX - bw - 12, 60]];
    if (long_) {
      s.push(['L', CX - bw - 95, 435], ['L', CX - bw - 55, 447], ['L', CX - bw, 215]);
    } else {
      s.push(['L', CX - bw - 100, 135], ['L', CX - bw - 60, 185], ['L', CX - bw, 150]);
    }
    s.push(['L', CX - bw, 545], ['L', CX + bw, 545]);
    if (long_) {
      s.push(['L', CX + bw, 215], ['L', CX + bw + 55, 447], ['L', CX + bw + 95, 435]);
    } else {
      s.push(['L', CX + bw, 150], ['L', CX + bw + 60, 185], ['L', CX + bw + 100, 135]);
    }
    s.push(['L', CX + bw + 12, 60], ['L', CX + 46, 38], ['Q', CX, neckY + 6, CX - 46, 38]);
    return s;
  }

  function tracePath(ctx, segs) {
    ctx.beginPath();
    segs.forEach(function (p) {
      if (p[0] === 'M') ctx.moveTo(p[1], p[2]);
      else if (p[0] === 'L') ctx.lineTo(p[1], p[2]);
      else ctx.quadraticCurveTo(p[1], p[2], p[3], p[4]);
    });
    ctx.closePath();
  }

  function shade(hex, amt) {         // lighten (+) / darken (−) a #RRGGBB colour
    var n = parseInt(hex.slice(1), 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
    function c(v) { return Math.max(0, Math.min(255, Math.round(v + amt))); }
    return 'rgb(' + c(r) + ',' + c(g) + ',' + c(b) + ')';
  }
  function isDark(hex) {
    var n = parseInt(hex.slice(1), 16);
    return ((n >> 16) * 299 + ((n >> 8) & 255) * 587 + (n & 255) * 114) / 1000 < 90;
  }

  // Product-photo mockups are used for the white polo and long-sleeve cuts.
  // Their loaded images are also drawn into exported proofs and 3D textures via renderSide.
  var PHOTO_TYPES = {
    tshirt: { front: 'assets/images/shirts/tshirt.png', back: 'assets/images/shirts/tshirt_back.png' },
    polo: { front: 'assets/images/shirts/shirt_05.png', back: 'assets/images/shirts/polo_back.png' },
    hoodie: { front: 'assets/images/shirts/hoodie.png', back: 'assets/images/shirts/hoodie_back.png' },
    longsleeve: { front: 'assets/images/shirts/shirt_03.png', back: 'assets/images/shirts/longsleeve_back.png' }
  };
  var shirtPhotos = {};
  Object.keys(PHOTO_TYPES).forEach(function (type) {
    shirtPhotos[type] = {};
    ['front', 'back'].forEach(function (side) {
      var image = new Image();
      image.onload = function () { render(); };
      image.src = PHOTO_TYPES[type][side];
      shirtPhotos[type][side] = image;
    });
  });

  // --- tinting: recolour the real product photo instead of drawing a generic outline ---
  // 1) cutout(): makes sure the photo has a transparent background (flood-fills near-white
  //    pixels connected to the image edge if the PNG came with an opaque white backdrop).
  // 2) tinted(): multiplies the chosen colour over the photo (keeps folds/shadows/collar detail),
  //    then clips to the shirt's own silhouette. Results are cached per photo + colour.
  var cutCache = {}, tintCache = {};
  function cutout(photo) {
    var key = photo.src;
    if (cutCache[key]) return cutCache[key];
    var c = document.createElement('canvas'); c.width = photo.naturalWidth; c.height = photo.naturalHeight;
    var x = c.getContext('2d'); x.drawImage(photo, 0, 0);
    try {
      var img = x.getImageData(0, 0, c.width, c.height), d = img.data, w = c.width, h = c.height;
      if (d[3] > 10) {                                   // opaque corner → remove the backdrop
        var seen = new Uint8Array(w * h), stack = [];
        var isBg = function (i) { return d[i * 4 + 3] > 0 && d[i * 4] >= 240 && d[i * 4 + 1] >= 240 && d[i * 4 + 2] >= 240; };
        var push = function (i) { if (!seen[i] && isBg(i)) { seen[i] = 1; stack.push(i); } };
        for (var a = 0; a < w; a++) { push(a); push((h - 1) * w + a); }
        for (var b = 0; b < h; b++) { push(b * w); push(b * w + w - 1); }
        while (stack.length) {
          var i = stack.pop(), px = i % w, py = (i / w) | 0;
          d[i * 4 + 3] = 0;
          if (px > 0) push(i - 1); if (px < w - 1) push(i + 1);
          if (py > 0) push(i - w); if (py < h - 1) push(i + w);
        }
        x.putImageData(img, 0, 0);
      }
    } catch (e) { /* tainted canvas → keep the photo as is */ }
    return (cutCache[key] = c);
  }
  function tinted(photo, color) {
    var key = photo.src + '|' + color;
    if (tintCache[key]) return tintCache[key];
    var cut = cutout(photo);
    var c = document.createElement('canvas'); c.width = cut.width; c.height = cut.height;
    var x = c.getContext('2d');
    x.drawImage(cut, 0, 0);
    x.globalCompositeOperation = 'multiply';
    x.fillStyle = color; x.fillRect(0, 0, c.width, c.height);
    x.globalCompositeOperation = 'destination-in';
    x.drawImage(cut, 0, 0);
    return (tintCache[key] = c);
  }

  // Pre-rendered colour variants: assets/images/shirts/colored/<type>_<side>_<RRGGBB>.webp
  // (loaded on demand; if a file is missing the live tint above is used instead)
  var COLOR_DIR = 'assets/images/shirts/colored/', coloredCache = {};
  function coloredPhoto(type, side, color) {
    var key = type + '_' + side + '_' + color.replace('#', '').toUpperCase();
    var e = coloredCache[key];
    if (!e) {
      e = coloredCache[key] = { img: new Image(), ok: false };
      e.img.onload = function () { e.ok = true; render(); };
      e.img.src = COLOR_DIR + key + '.webp';
    }
    return e.ok ? e.img : null;
  }

  function drawShirtBody(ctx, withOutline) {
    var typePhotos = shirtPhotos[state.type];
    var photo = typePhotos ? typePhotos[state.side] : null;
    if (photo && photo.complete && photo.naturalWidth) {
      var scale = Math.min(W / photo.naturalWidth, H / photo.naturalHeight);
      var width = photo.naturalWidth * scale;
      var height = photo.naturalHeight * scale;
      var white = state.color.toUpperCase() === '#FFFFFF';
      // white → original photo; catalog colours → pre-rendered shirt; any other (custom) colour → live tint
      var img = white ? photo : (coloredPhoto(state.type, state.side, state.color) || tinted(photo, state.color));
      ctx.drawImage(img, (W - width) / 2, (H - height) / 2, width, height);
      return;
    }
    var segs = outline(state.type, state.side);
    var line = isDark(state.color) ? 'rgba(255,255,255,.28)' : 'rgba(0,0,0,.22)';

    ctx.save();
    // hood sits behind the body
    if (state.type === 'hoodie') {
      ctx.beginPath();
      if (state.side === 'back') ctx.ellipse(CX, 70, 78, 52, 0, 0, Math.PI * 2);
      else ctx.ellipse(CX, 62, 70, 46, 0, 0, Math.PI * 2);
      ctx.fillStyle = shade(state.color, -14); ctx.fill();
      if (withOutline) { ctx.strokeStyle = line; ctx.lineWidth = 1.5; ctx.stroke(); }
    }
    tracePath(ctx, segs);
    ctx.fillStyle = state.color; ctx.fill();
    // soft fabric shading
    var g = ctx.createLinearGradient(0, 0, W, 0);
    g.addColorStop(0, 'rgba(0,0,0,.10)'); g.addColorStop(.5, 'rgba(255,255,255,.05)'); g.addColorStop(1, 'rgba(0,0,0,.10)');
    ctx.fillStyle = g; ctx.fill();
    if (withOutline) { ctx.strokeStyle = line; ctx.lineWidth = 2; ctx.stroke(); }

    // details
    ctx.strokeStyle = line; ctx.lineWidth = 1.5;
    if (state.side === 'front') {
      if (state.type === 'polo') {
        ctx.beginPath(); ctx.moveTo(CX - 46, 38); ctx.lineTo(CX - 18, 84); ctx.lineTo(CX, 70); ctx.lineTo(CX + 18, 84); ctx.lineTo(CX + 46, 38); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(CX, 70); ctx.lineTo(CX, 170); ctx.stroke();
        [105, 135, 160].forEach(function (y) { ctx.beginPath(); ctx.arc(CX, y, 3, 0, 6.3); ctx.stroke(); });
      }
      if (state.type === 'hoodie') {
        ctx.strokeRect(CX - 70, 400, 140, 70);                      // pouch pocket
        ctx.beginPath(); ctx.moveTo(CX - 22, 88); ctx.lineTo(CX - 26, 190); ctx.moveTo(CX + 22, 88); ctx.lineTo(CX + 26, 190); ctx.stroke(); // strings
      }
    }
    // sleeve seams
    var long_ = (state.type === 'longsleeve' || state.type === 'hoodie');
    var bw = state.type === 'hoodie' ? 134 : 125;
    ctx.beginPath();
    ctx.moveTo(CX - bw, long_ ? 215 : 150); ctx.lineTo(CX - bw + 4, 70);
    ctx.moveTo(CX + bw, long_ ? 215 : 150); ctx.lineTo(CX + bw - 4, 70);
    ctx.stroke();
    ctx.restore();
  }

  // ------------------------------------------------------------------ layer geometry
  var measureCtx = document.createElement('canvas').getContext('2d');

  function fontOf(el) { return (el.style === 'normal' ? '' : el.style + ' ') + el.size + 'px "' + el.font + '", sans-serif'; }
  function textLines(el) { return String(el.text).split('\n'); }

  function boxOf(el) {                // bounding box in canvas pixels
    if (el.type === 'image') return { x: el.x, y: el.y, w: el.width, h: el.height };
    if (el.type === 'emoji') return { x: el.x, y: el.y, w: el.size * 1.15, h: el.size * 1.15 };
    measureCtx.font = fontOf(el);
    var w = 0;
    textLines(el).forEach(function (l) { w = Math.max(w, measureCtx.measureText(l).width); });
    return { x: el.x, y: el.y, w: Math.max(w, 10), h: el.size * 1.25 * textLines(el).length };
  }

  function drawLayer(ctx, el) {
    ctx.save();
    ctx.textBaseline = 'top'; ctx.textAlign = 'left';
    if (el.type === 'text') {
      ctx.font = fontOf(el); ctx.fillStyle = el.color;
      textLines(el).forEach(function (l, i) { ctx.fillText(l, el.x, el.y + i * el.size * 1.25); });
    } else if (el.type === 'emoji') {
      ctx.font = el.size + 'px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",serif';
      ctx.fillText(el.char, el.x, el.y);
    } else if (el.type === 'image' && el.img && el.img.complete) {
      ctx.drawImage(el.img, el.x, el.y, el.width, el.height);
    }
    ctx.restore();
  }

  // Render one side onto any context. `ui` adds guides + selection handles.
  function renderSide(ctx, side, ui) {
    var keep = state.side; state.side = side;       // drawShirtBody reads state.side
    ctx.clearRect(0, 0, W, H);
    if (ui) { ctx.fillStyle = '#f8f8f8'; ctx.fillRect(0, 0, W, H); }
    drawShirtBody(ctx, ui);
    state.side = keep;

    if (ui) {                                         // print-area guide
      ctx.save(); ctx.setLineDash([6, 5]); ctx.strokeStyle = 'rgba(120,120,140,.35)'; ctx.lineWidth = 1;
      ctx.strokeRect(CX - 100, 120, 200, 300); ctx.restore();
    }
    state.layers[side].forEach(function (el) { drawLayer(ctx, el); });

    if (ui && side === state.side) {
      var el = selLayer();
      if (el) {
        var b = boxOf(el);
        ctx.save(); ctx.strokeStyle = '#e94560'; ctx.lineWidth = 1.5; ctx.setLineDash([4, 3]);
        ctx.strokeRect(b.x - 3, b.y - 3, b.w + 6, b.h + 6); ctx.setLineDash([]);
        ctx.fillStyle = '#e94560'; ctx.fillRect(b.x + b.w - 3, b.y + b.h - 3, 12, 12);
        ctx.restore();
      }
      if (!curLayers().length) {
        ctx.save(); ctx.fillStyle = 'rgba(120,120,120,.55)'; ctx.font = '13px Poppins, sans-serif'; ctx.textAlign = 'center';
        ctx.fillText('Add text, an image or a preset — it appears here instantly', CX, 470); ctx.restore();
      }
    }
  }

  var canvas = document.getElementById('shirt-canvas');
  var ctx2d = canvas.getContext('2d');
  canvas.width = W; canvas.height = H;
  canvas.style.touchAction = 'none';   // dragging on touch screens must not scroll the page

  function render() {
    renderSide(ctx2d, state.side, true);
    if (state.mode === '3d') viewer.refreshTextures();
  }

  // ------------------------------------------------------------------ price (AJAX → PHP)
  var quoteTimer = null, quoteSeq = 0;
  function hasKind(kinds) {
    return ['front', 'back'].some(function (s) { return state.layers[s].some(function (l) { return kinds.indexOf(l.type) >= 0; }); });
  }
  function requestQuote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(function () {
      var seq = ++quoteSeq;
      api('api/quote.php', { size: state.size, qty: state.qty, hasImage: hasKind(['image', 'emoji']), hasText: hasKind(['text']) })
        .done(function (r) {
          if (seq !== quoteSeq) return;                  // ignore stale replies
          $('#price-amount').text(r.total_fmt);
          $('#price-per').text(state.qty + (state.qty > 1 ? ' items · ' : ' item · ') + r.unit_fmt + ' each');
          $('#discount-badge').text(Math.round(r.discount * 100) + '% off!').css('display', r.discount > 0 ? 'inline-flex' : 'none');
        })
        .fail(function (m) { $('#price-amount').text('—'); showToast(m, 'error'); });
    }, 120);
  }

  // ------------------------------------------------------------------ layers panel
  var LAYER_ICON = { text: '🔤', image: '🖼️', emoji: '⭐' };
  function updateLayersPanel() {
    var $list = $('#layers-list').empty();
    var layers = curLayers();
    if (!layers.length) {
      $list.html('<div class="empty-layers"><span>📋</span>No design layers on the ' + state.side + ' yet.<br>Add text or upload an image.</div>');
      return;
    }
    $.each(layers, function (i, el) {
      var name = el.type === 'text' ? el.text.split('\n')[0] : (el.type === 'emoji' ? el.char + ' Preset' : 'Image');
      var meta = el.type === 'text' ? el.font + ' · ' + el.size + 'px' : (el.type === 'emoji' ? 'Preset' : 'Uploaded image');
      var $row = $('<div class="layer-item"></div>').attr('data-idx', i).css('outline', i === state.selected ? '2px solid #e94560' : 'none');
      $('<div class="layer-thumb"></div>').text(LAYER_ICON[el.type]).appendTo($row);
      var $info = $('<div class="layer-info"></div>').appendTo($row);
      $('<div class="layer-name"></div>').text(name).appendTo($info);
      $('<div class="layer-type"></div>').text(meta).appendTo($info);
      var $act = $('<div class="layer-actions"></div>').appendTo($row);
      $('<button type="button" class="layer-act-btn" data-act="up" title="Bring forward">⬆</button>').appendTo($act);
      $('<button type="button" class="layer-act-btn" data-act="down" title="Send back">⬇</button>').appendTo($act);
      $('<button type="button" class="layer-act-btn del" data-act="del" title="Delete">🗑️</button>').appendTo($act);
      $list.append($row);
    });
  }

  function changed() { render(); updateLayersPanel(); requestQuote(); }

  function select(i) {
    state.selected = i;
    var el = selLayer();
    if (el && el.type === 'text') loadTextControls(el);
    render(); updateLayersPanel();
  }

  $('#layers-list').on('click', '.layer-item', function (e) {
    var i = +$(this).data('idx'), act = $(e.target).closest('.layer-act-btn').data('act');
    var arr = curLayers();
    if (act === 'del') { arr.splice(i, 1); state.selected = -1; changed(); }
    else if (act === 'up' && i < arr.length - 1) { arr.splice(i + 1, 0, arr.splice(i, 1)[0]); state.selected = i + 1; changed(); }
    else if (act === 'down' && i > 0) { arr.splice(i - 1, 0, arr.splice(i, 1)[0]); state.selected = i - 1; changed(); }
    else if (!act) select(i);
  });

  // ------------------------------------------------------------------ canvas mouse / touch
  var drag = null;
  function pointer(e) {
    var o = e.originalEvent || e, t = o.touches && o.touches[0] ? o.touches[0] : (o.changedTouches && o.changedTouches[0]) || o;
    var r = canvas.getBoundingClientRect();
    return { x: (t.clientX - r.left) * (W / r.width), y: (t.clientY - r.top) * (H / r.height) };
  }
  function hit(p) {
    var arr = curLayers();
    var sel = selLayer();
    if (sel) {                                         // resize handle first
      var sb = boxOf(sel);
      if (p.x >= sb.x + sb.w - 6 && p.x <= sb.x + sb.w + 12 && p.y >= sb.y + sb.h - 6 && p.y <= sb.y + sb.h + 12) return { i: state.selected, resize: true };
    }
    for (var i = arr.length - 1; i >= 0; i--) {
      var b = boxOf(arr[i]);
      if (p.x >= b.x - 4 && p.x <= b.x + b.w + 4 && p.y >= b.y - 4 && p.y <= b.y + b.h + 4) return { i: i, resize: false };
    }
    return null;
  }

  $(canvas).on('mousedown touchstart', function (e) {
    var p = pointer(e), h = hit(p);
    if (!h) { drag = null; state.selected = -1; render(); updateLayersPanel(); return; }
    e.preventDefault();
    if (h.i !== state.selected) select(h.i);
    var el = selLayer(), b = boxOf(el);
    drag = { resize: h.resize, dx: p.x - el.x, dy: p.y - el.y, startX: p.x, b0: b,
             size0: el.size, w0: el.width, h0: el.height };
  });
  $(document).on('mousemove touchmove', function (e) {
    var p, el;
    if (!drag || !(el = selLayer())) return;
    p = pointer(e); e.preventDefault();
    if (drag.resize) {
      var k = Math.max(0.15, (drag.b0.w + (p.x - drag.startX)) / drag.b0.w);
      if (el.type === 'image') { el.width = Math.max(20, drag.w0 * k); el.height = Math.max(20, drag.h0 * k); }
      else el.size = Math.max(10, Math.min(300, Math.round(drag.size0 * k)));
    } else {
      el.x = p.x - drag.dx; el.y = p.y - drag.dy;
    }
    render();
  });
  $(document).on('mouseup touchend touchcancel', function () {
    if (drag) { drag = null; updateLayersPanel(); }
  });
  // cursor hint
  $(canvas).on('mousemove', function (e) {
    if (drag) return;
    var h = hit(pointer(e));
    canvas.style.cursor = h ? (h.resize ? 'nwse-resize' : 'move') : 'crosshair';
  });
  // keyboard: Delete removes the selected layer (unless typing in a field)
  $(document).on('keydown', function (e) {
    if ((e.key === 'Delete' || e.key === 'Backspace') && state.selected >= 0 && !$(e.target).is('input, textarea, select')) {
      e.preventDefault(); deleteSelected();
    }
  });

  function deleteSelected() {
    if (state.selected < 0) { showToast('Select an element first', 'warning'); return; }
    curLayers().splice(state.selected, 1); state.selected = -1; changed();
  }

  // ------------------------------------------------------------------ adding things
  function addLayer(el) {
    curLayers().push(el); state.selected = curLayers().length - 1; changed();
  }

  function addImageFromUpload(r) {
    var img = new Image();
    img.onload = function () {
      var k = Math.min(1, 200 / Math.max(img.naturalWidth, img.naturalHeight));
      var w = Math.round(img.naturalWidth * k), h = Math.round(img.naturalHeight * k);
      addLayer({ type: 'image', src: r.src, img: img, x: CX - w / 2, y: 270 - h / 2, width: w, height: h });
      showToast('Design uploaded! Drag to position, drag the corner to resize.', 'success');
    };
    img.onerror = function () { showToast('Could not read that image.', 'error'); };
    img.src = r.url;
  }

  function upload(file) {
    if (!file) return;
    if (!/^image\/(png|jpeg|gif|webp)$/.test(file.type)) { showToast('Please choose a PNG, JPG, GIF or WEBP image.', 'error'); return; }
    if (file.size > 10 * 1024 * 1024) { showToast('Image too large. Maximum 10MB.', 'error'); return; }
    var fd = new FormData(); fd.append('design', file);
    $('.upload-zone').addClass('drag-over');
    $.ajax({ url: 'api/upload.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(addImageFromUpload)
      .fail(function (xhr) { showToast((xhr.responseJSON && xhr.responseJSON.error) || 'Upload failed.', 'error'); })
      .always(function () { $('.upload-zone').removeClass('drag-over'); $('#design-upload').val(''); });
  }
  $('#design-upload').on('change', function () { upload(this.files[0]); });
  $('.upload-zone')
    .on('dragover', function (e) { e.preventDefault(); $(this).addClass('drag-over'); })
    .on('dragleave', function () { $(this).removeClass('drag-over'); })
    .on('drop', function (e) { e.preventDefault(); $(this).removeClass('drag-over'); upload(e.originalEvent.dataTransfer.files[0]); });

  // ---- text tool (controls edit the selected text layer live; "Add Text" makes a new one)
  var txt = { font: 'Poppins', color: '#000000', bold: false, italic: false };

  function textStyle() { return txt.italic && txt.bold ? 'italic bold' : txt.italic ? 'italic' : txt.bold ? 'bold' : 'normal'; }
  function loadTextControls(el) {
    $('#text-input').val(el.text);
    $('#text-size').val(el.size); $('#size-label').text(el.size);
    txt.font = el.font; txt.color = el.color; txt.bold = /bold/.test(el.style); txt.italic = /italic/.test(el.style);
    $('.font-btn').removeClass('active').filter(function () { return $(this).data('font') === el.font; }).addClass('active');
    $('.style-btn[data-style=bold]').toggleClass('active', txt.bold);
    $('.style-btn[data-style=italic]').toggleClass('active', txt.italic);
    $('#text-color-picker').val(el.color);
  }
  function liveEditText() {
    var el = selLayer();
    if (!el || el.type !== 'text') return;
    var v = $('#text-input').val();
    if (v.trim() !== '') el.text = v;
    el.font = txt.font; el.color = txt.color; el.style = textStyle(); el.size = +$('#text-size').val();
    render(); updateLayersPanel();
  }

  $('#text-input').on('input', liveEditText);
  $('#text-size').on('input', function () { $('#size-label').text(this.value); liveEditText(); });
  $('.font-btn').on('click', function () { $('.font-btn').removeClass('active'); $(this).addClass('active'); txt.font = $(this).data('font'); liveEditText(); });
  $('.style-btn').on('click', function () {
    $(this).toggleClass('active'); txt[$(this).data('style')] = $(this).hasClass('active'); liveEditText();
  });
  $('.quick-color').on('click', function () {
    $('.quick-color').removeClass('active'); $(this).addClass('active');
    txt.color = String($(this).data('color')).toUpperCase(); $('#text-color-picker').val(txt.color); liveEditText();
  });
  $('#text-color-picker').on('input', function () { $('.quick-color').removeClass('active'); txt.color = this.value.toUpperCase(); liveEditText(); });

  $('#btn-add-text').on('click', function () {
    var text = $.trim($('#text-input').val());
    if (!text) { showToast('Please enter some text first.', 'warning'); return; }
    var el = { type: 'text', text: text, font: txt.font, size: +$('#text-size').val() || 32, color: txt.color, style: textStyle(), x: 0, y: 0 };
    var b = boxOf(el); el.x = Math.max(10, CX - b.w / 2); el.y = 260;
    addLayer(el);
    showToast('Text added! Drag it to position.', 'success');
  });

  // ---- presets (emoji drawn as a layer)
  $('.preset-card').on('click', function () {
    var ch = $.trim($(this).find('.preset-preview').text());
    if (!ch) return;
    addLayer({ type: 'emoji', char: ch, size: 90, x: CX - 50, y: 230 });
    showToast('Preset "' + $.trim($(this).find('.preset-name').text()) + '" added!', 'success');
  });

  // ------------------------------------------------------------------ left panel controls
  $('.shirt-type-btn').on('click', function () {
    $('.shirt-type-btn').removeClass('active'); $(this).addClass('active');
    state.type = $(this).data('type'); render(); if (state.mode === '3d') viewer.build();
  });
  $('.size-btn').on('click', function () {
    $('.size-btn').removeClass('active'); $(this).addClass('active'); state.size = String($(this).data('size')); requestQuote();
  });
  $('.color-swatch').on('click', function () {
    $('.color-swatch').removeClass('active'); $(this).addClass('active');
    // .attr() (not .data()) so the custom colour picker's updated data-color is always read fresh
    state.color = String($(this).attr('data-color')).toUpperCase(); $('#color-name-display').text($(this).attr('data-name')); render();
  });
  function setQty(n) {
    state.qty = Math.max(1, Math.min(MAX_QTY, n)); $('#qty-display').text(state.qty); requestQuote();
  }
  $('#qty-minus').on('click', function () { setQty(state.qty - 1); });
  $('#qty-plus').on('click', function () { setQty(state.qty + 1); });

  $('.design-tab').on('click', function () {
    $('.design-tab, .tab-content').removeClass('active');
    $(this).addClass('active'); $('#' + $(this).data('tab')).addClass('active');
  });

  $('#ctrl-delete').on('click', deleteSelected);
  $('#ctrl-clear').on('click', function () {
    if (!curLayers().length) return;
    if (confirm('Clear everything on the ' + state.side + '? This cannot be undone.')) { state.layers[state.side] = []; state.selected = -1; changed(); }
  });

  // ------------------------------------------------------------------ view toggle (front / back / 3D)
  $('.view-btn').on('click', function () {
    var v = $(this).data('view');
    $('.view-btn').removeClass('active'); $(this).addClass('active');
    state.selected = -1;
    if (v === '3d') { enter3D(); return; }
    state.side = v; leave3D(); render(); updateLayersPanel();
  });
  $('#ctrl-preview-3d').on('click', function () { $('.view-btn[data-view="3d"]').trigger('click'); });

  // ------------------------------------------------------------------ 3D silhouettes
  // Outlines traced from the real shirt photos (canvas pixel coordinates, flat [x,y,x,y,...]).
  // front/back = what each photo covers; body = slightly smaller core that gets the 3D thickness.
  var SHAPES = {"tshirt":{"front":[200.7,75.6,193.1,80.9,174.4,90.2,122.0,113.8,109.1,122.2,97.1,144.4,88.7,163.6,61.6,240.4,63.3,242.7,101.6,256.9,121.1,262.2,140.2,264.4,145.1,268.9,146.9,272.9,148.7,314.2,146.9,367.6,134.9,499.1,142.4,504.0,164.7,506.7,223.8,506.2,279.8,508.4,292.2,507.1,336.7,506.7,356.7,504.0,362.4,501.3,364.7,499.1,353.1,379.6,350.4,308.0,350.9,274.7,352.7,270.7,358.9,265.3,373.6,264.4,390.0,260.9,420.7,250.7,438.0,243.1,428.2,213.8,401.1,144.9,392.7,128.4,384.2,117.8,366.9,108.9,318.0,87.6,297.6,76.0,273.1,82.2,237.1,83.1,217.6,80.4],"back":[201.6,74.2,194.9,80.0,182.4,86.2,121.6,112.4,109.6,121.3,96.7,144.9,86.9,167.1,60.7,241.3,106.0,258.2,122.0,262.2,140.2,264.4,145.1,268.9,146.9,272.9,148.7,312.9,146.9,367.6,134.9,498.7,138.0,501.8,144.2,504.4,172.7,508.0,329.6,508.0,346.4,506.7,359.8,503.1,364.7,498.2,357.1,429.3,353.1,372.4,350.4,309.8,350.9,272.9,352.7,268.9,357.6,264.4,363.3,263.1,373.6,264.0,392.7,260.0,435.8,244.0,438.0,242.2,410.9,167.6,394.0,131.6,386.9,120.4,382.4,116.9,362.4,106.7,321.1,88.9,304.7,80.4,296.7,74.2,258.0,78.7,235.3,78.2],"body":[120.7,121.8,114.0,126.7,97.6,159.6,79.8,206.2,70.4,237.3,102.0,250.2,142.0,258.2,150.4,265.3,153.6,272.0,154.9,341.8,142.4,496.0,146.4,498.2,163.8,500.0,222.9,499.6,259.3,501.8,337.6,500.0,355.8,497.3,357.6,490.2,346.4,380.4,344.2,272.0,347.3,265.3,355.8,258.2,364.2,256.4,373.1,257.3,391.8,253.3,422.9,242.7,429.1,238.2,403.8,167.6,388.7,135.1,382.0,124.9,376.7,120.9,296.2,83.1,272.2,88.9,238.0,89.8,215.8,86.7,202.9,82.7]},"polo":{"front":[211.3,50.7,202.9,56.0,195.8,68.4,188.2,76.9,113.6,113.8,106.4,121.8,97.6,134.7,93.1,145.3,68.2,227.6,67.3,241.3,100.7,253.3,123.8,258.7,135.8,260.0,140.7,264.4,142.4,268.9,143.3,323.6,140.7,386.2,137.6,398.7,128.2,500.9,134.9,506.2,150.0,510.7,225.1,519.1,312.2,516.9,349.6,510.7,362.0,504.9,362.9,502.2,354.9,443.6,348.2,369.8,347.3,314.7,349.1,289.3,347.3,284.9,346.9,273.8,349.1,268.9,354.0,264.9,374.0,264.0,432.7,243.6,431.3,229.3,399.3,140.4,390.4,121.8,380.7,110.2,303.8,75.6,296.7,68.4,287.8,54.2,281.6,49.8,237.6,48.4],"back":[212.2,51.6,206.0,56.0,198.4,68.4,189.6,78.2,123.3,108.4,112.7,115.1,101.6,129.8,93.6,146.7,75.3,198.2,66.9,226.7,66.0,241.8,87.8,250.2,110.0,256.4,136.2,259.1,142.4,264.4,144.2,268.9,145.1,334.2,142.9,386.7,132.2,499.1,141.6,504.4,165.6,510.2,214.9,514.2,288.7,514.2,323.8,512.0,352.2,507.1,362.9,502.7,366.0,500.0,353.1,363.1,351.3,326.2,352.7,269.3,354.4,265.3,359.8,260.9,373.6,260.4,390.0,256.9,432.7,242.7,431.3,228.0,399.3,141.3,390.9,125.3,380.7,112.4,309.6,80.0,301.1,71.6,292.7,56.0,286.9,51.6,276.7,49.3,260.7,48.4,228.2,48.9],"body":[214.0,57.8,193.1,83.6,122.4,116.0,112.7,125.3,103.3,140.0,95.3,160.4,74.9,228.4,76.2,238.2,109.6,248.9,138.0,252.9,147.8,260.9,150.9,268.0,151.8,335.1,149.6,387.6,139.3,495.1,144.7,498.7,168.2,504.0,215.3,507.6,288.2,507.6,331.8,504.4,351.8,500.4,354.4,498.7,341.6,370.7,340.7,313.8,342.4,290.2,340.2,272.9,343.8,265.3,354.4,256.4,362.0,253.8,380.7,252.4,415.3,242.2,424.2,238.2,424.7,230.2,392.7,142.2,385.6,128.9,375.8,116.9,306.0,85.3,291.3,72.0,280.7,56.9,236.7,55.1]},"hoodie":{"front":[187.8,69.3,182.0,73.8,174.4,95.1,163.3,115.6,159.3,119.6,127.3,133.8,122.0,137.3,111.8,148.0,102.9,168.9,93.6,199.6,85.6,234.7,73.1,306.2,65.6,363.1,61.6,405.8,61.6,451.1,65.6,473.3,72.2,485.3,72.7,510.2,78.0,512.0,98.4,512.0,109.6,510.2,111.3,508.4,111.3,495.6,114.0,480.4,120.7,472.4,126.9,470.7,134.9,472.0,140.7,478.7,142.0,501.3,147.8,504.4,157.6,506.2,341.6,506.2,351.3,504.4,357.1,500.4,358.0,476.4,362.9,470.7,371.3,468.9,378.4,471.1,386.4,482.7,388.2,488.4,388.2,508.0,390.0,510.7,407.3,512.4,422.9,512.0,426.9,510.7,428.2,485.3,434.4,473.3,438.0,449.8,438.0,428.0,434.0,384.0,422.4,300.0,408.2,220.0,398.0,179.1,391.3,160.0,383.3,143.6,370.0,133.3,339.8,120.4,334.4,115.1,321.1,88.4,316.7,74.7,305.1,67.1,282.0,61.3,233.6,60.4,206.4,63.1],"back":[179.3,72.4,174.9,78.2,172.2,92.9,162.0,115.1,158.9,118.2,129.6,131.6,120.2,137.8,111.8,146.2,100.2,174.2,90.9,207.1,81.6,250.7,71.3,311.6,65.6,358.2,61.6,411.6,63.3,460.4,66.4,472.4,72.7,484.0,73.1,508.9,78.9,511.1,102.0,511.1,110.4,509.3,112.2,507.6,112.2,494.2,114.4,481.3,121.1,472.9,126.9,471.6,134.9,472.4,139.8,476.4,141.6,480.4,142.0,500.9,144.2,503.6,151.3,506.2,180.2,508.4,341.1,508.0,350.4,506.2,356.7,502.7,358.0,499.6,358.4,478.7,363.3,472.4,372.2,470.7,378.4,472.4,386.4,482.7,388.2,489.3,389.6,510.2,394.4,512.0,407.3,512.4,422.9,512.0,426.4,510.7,427.3,487.1,434.0,474.7,438.0,445.3,438.0,414.7,434.4,369.3,422.0,287.1,408.2,215.6,398.0,176.9,385.1,144.9,371.8,134.2,338.9,119.1,334.9,115.1,332.7,108.4,326.9,98.7,322.9,86.7,321.6,76.9,319.3,73.8,310.0,68.4,298.4,65.3,254.9,60.9,232.7,60.9,198.9,65.3],"body":[191.3,74.7,187.3,77.8,181.1,96.0,168.7,119.1,162.9,124.9,130.9,139.1,118.0,150.7,109.6,169.8,100.2,200.4,79.8,307.1,69.6,391.6,69.6,455.6,71.8,468.0,79.3,483.1,80.2,503.1,103.3,504.0,106.0,483.6,109.6,475.1,117.1,467.1,127.8,464.0,138.4,466.7,145.1,472.9,148.2,479.6,148.2,496.4,151.3,498.7,347.3,498.7,350.4,496.4,350.4,478.2,353.6,471.1,359.3,465.3,370.4,462.2,382.0,465.8,386.9,470.7,394.0,484.4,396.7,504.9,419.3,504.9,420.7,486.2,427.3,473.8,431.3,444.4,428.2,392.4,419.3,324.0,403.3,229.8,391.3,180.0,377.6,146.7,369.6,140.4,336.2,125.8,329.1,118.7,316.2,93.3,311.3,78.7,303.3,73.3,255.8,67.6,231.8,67.6,201.1,71.6]},"longsleeve":{"front":[121.6,105.3,118.9,108.0,110.4,124.0,101.1,154.7,83.3,244.4,73.6,308.4,65.6,384.9,63.3,424.0,63.3,447.1,65.1,462.7,70.4,480.0,69.6,504.0,82.9,507.1,106.4,506.7,108.7,504.0,110.4,491.1,112.2,487.1,116.2,483.1,121.6,481.8,131.3,483.6,135.8,488.9,136.7,497.3,141.6,500.4,149.6,502.7,182.9,504.0,215.3,499.1,241.1,499.1,283.3,504.9,300.2,504.4,327.8,500.4,358.4,500.9,364.7,497.3,365.1,491.6,370.4,484.9,380.2,483.6,385.1,484.9,390.4,491.6,392.7,508.9,398.4,510.2,411.8,510.2,432.2,507.1,432.7,478.7,436.2,471.1,437.1,465.3,437.1,433.3,434.4,387.1,430.0,344.9,418.0,254.7,406.0,180.9,393.6,130.7,382.9,106.7,363.3,94.2,310.0,69.3,302.9,64.9,291.3,69.3,278.0,71.6,248.7,72.4,228.7,70.2,214.9,66.7,207.8,66.7,202.4,64.4,133.6,98.2],"back":[119.3,106.2,110.9,119.1,99.3,153.8,85.1,221.3,73.6,292.9,63.3,403.1,63.3,461.8,65.1,470.2,69.1,477.8,69.1,505.3,82.0,508.4,106.9,507.6,108.7,505.3,110.9,486.7,112.7,482.7,117.6,478.2,125.1,476.9,131.3,478.7,135.8,483.1,137.1,486.7,137.1,497.3,138.9,499.1,151.3,504.0,166.0,506.2,198.4,506.2,234.4,502.7,261.1,503.1,290.9,506.2,318.9,506.2,345.1,504.0,362.0,500.0,364.7,496.9,364.7,488.4,366.4,484.4,371.3,480.0,378.9,478.7,385.1,480.4,390.4,487.1,393.6,508.4,413.6,509.3,431.3,507.1,432.2,477.8,436.7,467.6,437.1,423.6,434.0,378.7,426.0,308.9,416.2,240.0,406.0,180.9,394.4,135.6,382.4,108.0,372.7,101.3,325.6,79.6,297.1,63.6,285.1,66.7,264.7,69.3,230.0,68.9,204.2,63.6,196.7,68.9,136.2,96.9],"body":[125.1,110.7,115.8,128.4,105.6,165.3,90.0,245.3,80.2,309.3,70.0,424.9,70.0,446.2,72.7,466.7,77.1,479.1,77.6,498.7,82.0,500.4,101.1,500.0,107.3,479.1,113.1,473.3,123.8,470.2,134.9,473.3,143.3,484.0,144.2,493.8,150.0,496.0,182.9,497.3,216.2,492.4,240.2,492.4,282.4,498.2,301.1,497.8,328.7,493.8,356.7,493.8,361.1,480.9,366.9,475.1,378.0,472.0,388.7,475.1,394.4,480.9,400.2,502.2,424.2,501.3,425.6,476.9,430.0,466.7,430.4,434.2,423.3,345.8,399.3,181.8,387.8,136.4,377.6,112.4,369.1,106.7,299.3,73.3,269.6,79.1,249.6,79.1,205.6,72.4,194.0,76.4]}};
  function shapeFromFlat(a) {
    var sh = new THREE.Shape();
    for (var i = 0; i < a.length; i += 2) {
      if (i === 0) sh.moveTo(a[i], H - a[i + 1]); else sh.lineTo(a[i], H - a[i + 1]);
    }
    sh.closePath();
    return sh;
  }

  // ------------------------------------------------------------------ 3D viewer
  var viewer = {
    ready: false, failed: false, raf: 0,
    init: function () {
      if (this.ready || this.failed) return !this.failed;
      var $wrap = $('#canvas-wrapper');
      var $mount = $('<div id="threejs-mount" style="width:100%;height:100%;cursor:grab"></div>').appendTo($wrap);
      try {
        var w = $wrap.width(), h = $wrap.height();
        this.renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: false });
        this.renderer.setSize(w, h); this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        $mount.append(this.renderer.domElement);
      } catch (err) {
        this.failed = true; $mount.remove(); return false;
      }
      this.$mount = $mount;
      this.scene = new THREE.Scene(); this.scene.background = new THREE.Color(0xf0f2f5);
      this.camera = new THREE.PerspectiveCamera(40, w / h, 0.1, 100); this.camera.position.set(0, 0.1, 6.4);
      this.scene.add(new THREE.AmbientLight(0xffffff, 0.62));
      var d1 = new THREE.DirectionalLight(0xffffff, 0.5); d1.position.set(3, 4, 6); this.scene.add(d1);
      var d2 = new THREE.DirectionalLight(0xffffff, 0.35); d2.position.set(-3, 2, -6); this.scene.add(d2);
      this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
      this.controls.enableDamping = true; this.controls.minDistance = 3; this.controls.maxDistance = 10; this.controls.enablePan = false;
      var self = this;
      $(window).on('resize.v3d', function () {
        if (state.mode !== '3d') return;
        var ww = $wrap.width(), hh = $wrap.height();
        self.camera.aspect = ww / hh; self.camera.updateProjectionMatrix(); self.renderer.setSize(ww, hh);
      });
      this.ready = true; this.build(); return true;
    },
    build: function () {                                  // (re)create the shirt for the current type
      if (!this.ready) return;
      if (this.group) { this.scene.remove(this.group); this.group.traverse(function (o) { if (o.geometry) o.geometry.dispose(); }); }
      var shape, shapeF, shapeB, photoShape = SHAPES[state.type];
      if (photoShape) {                                   // follow the real photo silhouette
        shape = shapeFromFlat(photoShape.body);
        shapeF = shapeFromFlat(photoShape.front);
        shapeB = shapeFromFlat(photoShape.back);
      } else {                                            // fallback: generic outline
        shape = new THREE.Shape();
        outline(state.type, 'front').forEach(function (p) {
          if (p[0] === 'M') shape.moveTo(p[1], H - p[2]);
          else if (p[0] === 'L') shape.lineTo(p[1], H - p[2]);
          else shape.quadraticCurveTo(p[1], H - p[2], p[3], H - p[4]);
        });
        shapeF = shapeB = shape;
      }
      var depth = 34, bevel = 7;
      var body = new THREE.ExtrudeGeometry(shape, { depth: depth, bevelEnabled: true, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 3, curveSegments: 14 });
      var front = new THREE.ShapeGeometry(shapeF, 14), back = new THREE.ShapeGeometry(shapeB, 14);
      var zOff = depth / 2 + bevel + 0.6;                      // just outside the bevelled body
      body.translate(-CX, -H / 2, -depth / 2);
      front.translate(-CX, -H / 2, zOff);
      back.translate(-CX, -H / 2, 0); back.rotateY(Math.PI); back.translate(0, 0, -zOff);   // UVs are already baked → texture stays aligned

      function tex() {
        var c = document.createElement('canvas'); c.width = W; c.height = H;
        var t = new THREE.CanvasTexture(c); t.repeat.set(1 / W, 1 / H); t.anisotropy = 4; return t;
      }
      this.texF = tex(); this.texB = tex();
      this.bodyMat = new THREE.MeshStandardMaterial({ color: state.color, roughness: 0.92, metalness: 0 });
      this.group = new THREE.Group();
      this.group.add(new THREE.Mesh(body, this.bodyMat));
      this.group.add(new THREE.Mesh(front, new THREE.MeshStandardMaterial({ map: this.texF, roughness: 0.92, metalness: 0 })));
      this.group.add(new THREE.Mesh(back, new THREE.MeshStandardMaterial({ map: this.texB, roughness: 0.92, metalness: 0 })));
      this.group.scale.setScalar(0.0068);
      this.scene.add(this.group);
      this.refreshTextures();
    },
    refreshTextures: function () {
      if (!this.ready || !this.group) return;
      this.bodyMat.color.set(state.color);
      renderSide(this.texF.image.getContext('2d'), 'front', false); this.texF.needsUpdate = true;
      renderSide(this.texB.image.getContext('2d'), 'back', false);  this.texB.needsUpdate = true;
    },
    start: function () {
      var self = this; cancelAnimationFrame(this.raf);
      (function loop() { self.raf = requestAnimationFrame(loop); self.controls.update(); self.renderer.render(self.scene, self.camera); })();
    },
    stop: function () { cancelAnimationFrame(this.raf); }
  };

  function enter3D() {
    if (!viewer.init()) {
      showToast('3D preview is not supported by this browser/graphics card. Using 2D view.', 'warning');
      $('.view-btn').removeClass('active').filter('[data-view="' + state.side + '"]').addClass('active');
      return;
    }
    state.mode = '3d';
    $(canvas).hide(); viewer.$mount.show(); $('.canvas-hint').hide(); $('#rotate-hint').css('visibility', 'visible');
    viewer.build(); viewer.start();
    // size may have changed while hidden
    var $w = $('#canvas-wrapper'); viewer.renderer.setSize($w.width(), $w.height());
    viewer.camera.aspect = $w.width() / $w.height(); viewer.camera.updateProjectionMatrix();
  }
  function leave3D() {
    if (state.mode !== '3d') return;
    state.mode = '2d'; viewer.stop(); viewer.$mount.hide(); $(canvas).show(); $('.canvas-hint').show(); $('#rotate-hint').css('visibility', 'hidden');
  }

  // ------------------------------------------------------------------ add to order
  function serialise(arr) {
    return arr.map(function (l) {
      var o = { type: l.type, x: Math.round(l.x), y: Math.round(l.y) };
      if (l.type === 'text') $.extend(o, { text: l.text, font: l.font, size: l.size, color: l.color, style: l.style });
      if (l.type === 'image') $.extend(o, { src: l.src, width: Math.round(l.width), height: Math.round(l.height) });
      if (l.type === 'emoji') $.extend(o, { char: l.char, size: l.size });
      return o;
    });
  }
  function previewPNG(side) {
    var c = document.createElement('canvas'); c.width = W; c.height = H;
    var cx = c.getContext('2d'); cx.fillStyle = '#f8f8f8'; cx.fillRect(0, 0, W, H);
    var tmp = document.createElement('canvas'); tmp.width = W; tmp.height = H;
    renderSide(tmp.getContext('2d'), side, false);
    cx.drawImage(tmp, 0, 0);
    return c.toDataURL('image/png');
  }

  $('#btn-add-order').on('click', function () {
    var $b = $(this);
    if (!state.layers.front.length && !state.layers.back.length) {
      showToast('Add some text, an image or a preset before ordering.', 'warning'); return;
    }
    $b.prop('disabled', true).text('Saving your design…');
    api('api/save_draft.php', {
      type: state.type, size: state.size, color: state.color, qty: state.qty,
      layers: { front: serialise(state.layers.front), back: serialise(state.layers.back) },
      preview_front: previewPNG('front'),
      preview_back: state.layers.back.length ? previewPNG('back') : null
    }).done(function (r) {
      if (r.redirect.indexOf('auth.php') === 0) showToast('Please sign in to place your order.', 'info');
      location.href = r.redirect;
    }).fail(function (m) {
      showToast(m, 'error'); $b.prop('disabled', false).html('🛒 Add to Order &amp; Checkout');
    });
  });

  // ------------------------------------------------------------------ go
  render(); updateLayersPanel(); requestQuote();

})(jQuery, window.THREE);
