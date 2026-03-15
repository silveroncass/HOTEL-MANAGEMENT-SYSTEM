<?php
// Philippines Timezone — UTC+8
date_default_timezone_set('Asia/Manila');
// ============================================================
// GrandStay HMS — Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'grandstay_hms');
define('SITE_NAME', 'GrandStay');
define('SITE_URL', 'http://localhost/grandstay');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Secure session config — prevents session loss on redirect
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin($role = null) {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        // redirect to correct dashboard
        if ($_SESSION['role'] === 'admin')  { header('Location: ' . SITE_URL . '/admin/dashboard.php'); exit; }
        if ($_SESSION['role'] === 'staff')  { header('Location: ' . SITE_URL . '/staff/dashboard.php'); exit; }
        if ($_SESSION['role'] === 'user')   { header('Location: ' . SITE_URL . '/user/booking.php');    exit; }
    }
}

function logLogin($user_id, $username, $status) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO login_logs (user_id, username, ip_address, user_agent, status) VALUES (?,?,?,?,?)");
    $stmt->execute([$user_id, $username, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $status]);
}

function hashPassword($plain) {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($plain, $hash) {
    // Dev fallback: plain text match (remove in production)
    if ($plain === $hash) return true;
    return password_verify($plain, $hash);
}

function formatPHP($amount) {
    return '₱' . number_format($amount, 2);
}
?>
