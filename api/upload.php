<?php
// AJAX endpoint: artwork upload (multipart, field name "design"). Stored under a random name.
require_once __DIR__ . '/../includes/functions.php';
api_post();

$_SESSION['upload_count'] = ($_SESSION['upload_count'] ?? 0) + 1;
if ($_SESSION['upload_count'] > 40) fail('Upload limit reached for this session.', 429);

if (empty($_FILES['design']) || $_FILES['design']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['design']['error'] ?? UPLOAD_ERR_NO_FILE;
    fail(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'File is larger than the server allows (check upload_max_filesize in php.ini).'
        : 'Upload failed. Please try again.');
}
$f = $_FILES['design'];
if ($f['size'] > MAX_UPLOAD_BYTES) fail('Image too large. Maximum is 10 MB.');
if (!is_uploaded_file($f['tmp_name'])) fail('Invalid upload.');

$info = @getimagesize($f['tmp_name']);
$types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
if (!$info || !isset($types[$info[2]])) fail('Please upload a PNG, JPG, GIF or WEBP image.');
if ($info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 40000000) fail('Image dimensions are too large (max 8000px).');

$name = bin2hex(random_bytes(16)) . '.' . $types[$info[2]];
$dir = __DIR__ . '/../uploads/artwork';
if (!is_dir($dir)) mkdir($dir, 0775, true);
if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) fail('Could not save the file.', 500);

json_out(['ok' => true, 'src' => $name, 'url' => 'uploads/artwork/' . $name, 'width' => $info[0], 'height' => $info[1]]);
