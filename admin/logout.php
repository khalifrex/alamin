<?php
// Plain link version of "Sign out" (the buttons use the AJAX endpoint instead).
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['admin']);
header('Location: login.php');
exit;
