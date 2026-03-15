<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') header('Location: admin/dashboard.php');
    elseif ($_SESSION['role'] === 'staff') header('Location: staff/dashboard.php');
    else header('Location: user/booking.php');
} else {
    header('Location: login.php');
}
exit;
