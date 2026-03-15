<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$tab      = trim($_POST['role'] ?? 'admin'); // 'admin' tab or 'user' tab

if (!$username || !$password) {
    echo json_encode(['success' => false, 'message' => 'Please enter username and password.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.username = ? AND u.is_active = 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !verifyPassword($password, $user['password'])) {
    logLogin($user['user_id'] ?? null, $username, 'failed');
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit;
}

$role = strtolower($user['role_name']); // normalize to lowercase

// Tab = 'admin' allows both admin and staff roles
// Tab = 'user'  allows only guest/user role
if ($tab === 'user' && $role !== 'user') {
    logLogin($user['user_id'], $username, 'failed');
    echo json_encode(['success' => false, 'message' => 'Please use the Staff / Admin tab to log in.']);
    exit;
}
if ($tab !== 'user' && $role === 'user') {
    logLogin($user['user_id'], $username, 'failed');
    echo json_encode(['success' => false, 'message' => 'Please use the Guest tab to log in.']);
    exit;
}

// Set session — always use lowercase role
$_SESSION['user_id']    = $user['user_id'];
$_SESSION['username']   = $user['username'];
$_SESSION['role']       = $role;
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name']  = $user['last_name'];
$_SESSION['email']      = $user['email'];

logLogin($user['user_id'], $username, 'success');

$redirect = match($role) {
    'admin' => SITE_URL . '/admin/dashboard.php',
    'staff' => SITE_URL . '/staff/dashboard.php',
    'user'  => SITE_URL . '/user/booking.php',
    default => SITE_URL . '/login.php',
};

echo json_encode(['success' => true, 'redirect' => $redirect]);
