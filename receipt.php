<?php
// staff/receipt.php — same receipt but requires staff login
// Simply include the admin receipt logic with staff auth check
require_once __DIR__ . '/../includes/config.php';
requireLogin('staff');

// Re-route to shared receipt logic
$_SESSION['_receipt_role_ok'] = true;
include __DIR__ . '/../admin/receipt.php';
