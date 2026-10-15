<?php
// AJAX endpoint: "Add to order" — validates the design, stores it in the PHP session for checkout.
require_once __DIR__ . '/../includes/functions.php';
$in = api_post();
$d = clean_design($in);

$front = save_png_dataurl($in['preview_front'] ?? null);
$back  = null;
if (!empty($d['layers']['back'])) $back = save_png_dataurl($in['preview_back'] ?? null);
if (!$front) fail('Could not capture your design preview. Please try again.');

$_SESSION['draft'] = $d + ['front' => $front, 'back' => $back];
json_out(['ok' => true, 'redirect' => current_user() ? 'checkout.php' : 'auth.php?next=checkout.php']);
